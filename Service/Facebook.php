<?php
/**
 * This file is part of the Smatyas/FacebookBundle.
 *
 * (c) Mátyás Somfai <somfai.matyas@gmail.com>
 * Created at 2017.02.01.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Smatyas\FacebookBundle\Service;

use Smatyas\FacebookBundle\Client\ProfilingFacebook;
use Smatyas\FacebookBundle\Event\UpdateReceivedEvent;
use Smatyas\FacebookBundle\Exception\FacebookServiceException;
use Facebook\Authentication\AccessToken;
use Monolog\Logger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Stopwatch\Stopwatch;

class Facebook
{
    /**
     * @var ProfilingFacebook
     */
    private $client;

    /**
     * @var string
     */
    private $config;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * Facebook constructor.
     *
     * @param array $config
     * @param Logger $logger
     * @param EventDispatcherInterface $eventDispatcher
     * @param Stopwatch $stopwatch
     */
    public function __construct(
        $config,
        Logger $logger,
        EventDispatcherInterface $eventDispatcher,
        Stopwatch $stopwatch = null
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;

        $clientConfig = $config;
        unset($clientConfig['webhook_verify_token']);
        $this->client = new ProfilingFacebook($clientConfig, $stopwatch);
    }

    /**
     * @return ProfilingFacebook
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @return array
     */
    public function getProfiles()
    {
        return $this->getClient()->getProfiles();
    }

    /**
     * Get the webhook verify token.
     *
     * @return string|null
     */
    public function getWebhookVerifyToken()
    {
        if (isset($this->config['webhook_verify_token'])) {
            return $this->config['webhook_verify_token'];
        }

        return null;
    }

    /**
     * Get a long-lived page access token for the given page and user.
     *
     * @param $pageId
     * @param $userAccessToken
     * @return null|string
     * @throws \Exception
     */
    public function getLongLivedPageAccessToken($pageId, $userAccessToken)
    {
        $this->logger->addInfo('Getting long-lived page access token for page: ' . $pageId);
        $accessToken = $this->validateAccessToken($userAccessToken);

        // Exchanges a short-lived access token for a long-lived one
        $oAuth2Client = $this->getClient()->getOAuth2Client();
        $longLivedAccessToken = $oAuth2Client->getLongLivedAccessToken($accessToken);

        // Get page access token - https://developers.facebook.com/docs/facebook-login/access-tokens#pagetokens
        /** @var \Facebook\FacebookResponse $responsePage */
        $responsePage = $this->getClient()->get('/me/accounts', $longLivedAccessToken); // https://developers.facebook.com/docs/graph-api/reference/user/accounts/
        $graphPages = $responsePage->getGraphEdge('GraphPage');
        foreach ($graphPages as $page) {
            /** @var $page \Facebook\GraphNodes\GraphPage */
            if ($pageId === $page->getId()) {
                return $page->getAccessToken();
            }
        }

        throw new FacebookServiceException('The user does not have access to this page.');
    }

    /**
     * Get user data.
     *
     * @param $token
     * @param string $fields
     * @return \Facebook\GraphNodes\GraphNode
     */
    public function getUserData($token, $fields = 'name')
    {
        $this->logger->addInfo('Fetching user data from facebook.');
        $accessToken = $this->validateAccessToken($token);
        $res = $this->getClient()->get('/me?fields=' . $fields, $accessToken);
        $node = $res->getGraphNode();
        $this->logger->addDebug('Response data: ' . $node->asJson());

        return $node;
    }

    /**
     * Get page data.
     *
     * @param $pageId
     * @param $token
     * @param string $fields
     * @return \Facebook\GraphNodes\GraphNode
     */
    public function getPageData($pageId, $token = null, $fields = 'name,link,about,category')
    {
        $this->logger->addInfo('Fetching page data from facebook: ' . $pageId);
        $res = $this->getClient()->get(
            sprintf('/%s?fields=%s', $pageId, $fields),
            $token
        );
        $node = $res->getGraphNode();
        $this->logger->addDebug('Response data: ' . $node->asJson());

        return $node;
    }

    /**
     * Handle incoming webhook from facebook.
     *
     * @param Request $request
     * @return Response
     */
    public function handleWebhook(Request $request)
    {
        switch ($request->getMethod()) {
            case 'GET':
                // Subscription verification
                if ($request->query->get('hub_mode') === 'subscribe'
                    && $request->query->get('hub_verify_token') === $this->getWebhookVerifyToken()
                    && $request->query->has('hub_challenge')
                ) {
                    return new Response($request->query->get('hub_challenge'));
                } else {
                    if ($request->query->get('hub_mode') !== 'subscribe') {
                        $this->logger->addError('Subscription verification error: hub_mode mismatch.');
                    }
                    if ($request->query->get('hub_verify_token') !== $this->getWebhookVerifyToken()) {
                        $this->logger->addError('Subscription verification error hub_verify_token mismatch.');
                    }
                    if (!$request->query->has('hub_challenge')) {
                        $this->logger->addError('Subscription verification error: hub_challenge is missing.');
                    }
                    throw new NotFoundHttpException();
                }
                break;

            case 'POST':
                // Data update
                $this->logger->addInfo('Webhook message received: ' . $request->getContent());

                if (!$this->isValidWebhookRequest($request)) {
                    $this->logger->addError('Message signature mismatch.');
                    throw new NotFoundHttpException('Message signature mismatch.');
                }

                // Dispatch the received event to the listeners.
                $this->logger->addDebug('Dispatching update received event.');
                $event = new UpdateReceivedEvent($request->getContent());
                $this->eventDispatcher->dispatch(UpdateReceivedEvent::NAME, $event);

                return new Response();
                break;

            default:
                throw new NotFoundHttpException();
        }
    }

    /**
     * Verifies the webhook request.
     *
     * @param Request $request
     * @return bool
     */
    private function isValidWebhookRequest(Request $request)
    {
        $hash = 'sha1=' . hash_hmac('sha1', $request->getContent(), $this->config['app_secret']);
        $this->logger->addDebug(sprintf('Signature: %s (%s)', $hash, $request->headers->get('X-Hub-Signature')));
        return $hash === $request->headers->get('X-Hub-Signature');
    }

    /**
     * Hide a comment.
     *
     * @param $commentId
     * @param null $token
     * @return \Facebook\FacebookResponse
     * @internal param $message
     */
    public function hideComment($commentId, $token = null)
    {
        $this->logger->addInfo('Hiding comment: ' . $commentId);
        $res = $this->getClient()->post(
            sprintf('/%s', $commentId),
            ['is_hidden' => true],
            $token
        );
        $this->logger->addDebug('Response data: ' . $res->getBody());

        return $res;
    }

    /**
     * Show a comment.
     *
     * @param $commentId
     * @param null $token
     * @return \Facebook\FacebookResponse
     * @internal param $message
     */
    public function showComment($commentId, $token = null)
    {
        $this->logger->addInfo('Showing comment: ' . $commentId);
        $res = $this->getClient()->post(
            sprintf('/%s', $commentId),
            ['is_hidden' => false],
            $token
        );
        $this->logger->addDebug('Response data: ' . $res->getBody());

        return $res;
    }

    /**
     * Update a comment.
     *
     * @param $commentId
     * @param $message
     * @param null $token
     * @return \Facebook\FacebookResponse
     * @internal param $message
     */
    public function updateComment($commentId, $message, $token = null)
    {
        $this->logger->addInfo('Updating comment: ' . $commentId);
        $res = $this->getClient()->post(
            sprintf('/%s', $commentId),
            ['message' => $message],
            $token
        );
        $this->logger->addDebug('Response data: ' . $res->getBody());

        return $res;
    }

    /**
     * Delete a comment.
     *
     * @param $commentId
     * @param null $token
     * @return \Facebook\FacebookResponse
     * @internal param $message
     */
    public function deleteComment($commentId, $token = null)
    {
        $this->logger->addInfo('Deleting comment: ' . $commentId);
        $res = $this->getClient()->delete(
            sprintf('/%s', $commentId),
            [],
            $token
        );
        $this->logger->addDebug('Response data: ' . $res->getBody());

        return $res;
    }

    /**
     * Hide a post.
     *
     * @param $postId
     * @param null $token
     * @return \Facebook\FacebookResponse
     * @internal param $message
     */
    public function hidePost($postId, $token = null)
    {
        $this->logger->addInfo('Hiding post: ' . $postId);
        $res = $this->getClient()->post(
            sprintf('/%s', $postId),
            ['is_hidden' => true],
            $token
        );
        $this->logger->addDebug('Response data: ' . $res->getBody());

        return $res;
    }

    /**
     * Show a post.
     *
     * @param $postId
     * @param null $token
     * @return \Facebook\FacebookResponse
     * @internal param $message
     */
    public function showPost($postId, $token = null)
    {
        $this->logger->addInfo('Showing post: ' . $postId);
        $res = $this->getClient()->post(
            sprintf('/%s', $postId),
            ['is_hidden' => false],
            $token
        );
        $this->logger->addDebug('Response data: ' . $res->getBody());

        return $res;
    }

    /**
     * Update a post.
     *
     * @param $postId
     * @param $message
     * @param null $token
     * @return \Facebook\FacebookResponse
     * @internal param $message
     */
    public function updatePost($postId, $message, $token = null)
    {
        $this->logger->addInfo('Updating post: ' . $postId);
        $res = $this->getClient()->post(
            sprintf('/%s', $postId),
            ['message' => $message],
            $token
        );
        $this->logger->addDebug('Response data: ' . $res->getBody());

        return $res;
    }

    /**
     * Delete a post.
     *
     * @param $postId
     * @param null $token
     * @return \Facebook\FacebookResponse
     * @internal param $message
     */
    public function deletePost($postId, $token = null)
    {
        $this->logger->addInfo('Deleting post: ' . $postId);
        $res = $this->getClient()->delete(
            sprintf('/%s', $postId),
            [],
            $token
        );
        $this->logger->addDebug('Response data: ' . $res->getBody());

        return $res;
    }

    /**
     * Validates the given access token.
     *
     * @param $token
     * @return AccessToken
     * @throws FacebookServiceException
     */
    public function validateAccessToken($token)
    {
        $this->logger->addInfo('Validating token: ' . $token);
        $accessToken = new AccessToken($token);
        $oAuth2Client = $this->getClient()->getOAuth2Client();
        $tokenMetadata = $oAuth2Client->debugToken($accessToken);
        $tokenMetadata->validateExpiration();
        if (!$tokenMetadata->getIsValid()) {
            $this->logger->addDebug('Invalid token: ' . $token);
            throw new FacebookServiceException('The access token is invalid.');
        }
        $this->logger->addDebug('Valid token: ' . $token);

        return $accessToken;
    }
}
