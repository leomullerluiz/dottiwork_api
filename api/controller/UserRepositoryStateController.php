<?php

class UserRepositoryStateController extends BaseController
{
    private $states = ['saved', 'ignored', 'researching', 'working', 'pull_request_sent', 'contributed', 'archived'];

    public function index(Request $request)
    {
        $user = $this->requireToken($request);
        Response::success([
            'items' => UserRepositoryState::listByUser($user['id'], [
                'state' => $request->getQuery('state'),
                'limit' => $this->limit($request, 50, 100),
                'cursor' => $request->getQuery('cursor'),
            ]),
            'pagination' => ['next_cursor' => null],
        ]);
    }

    public function setState(Request $request, $params)
    {
        $user = $this->requireToken($request);
        $body = $this->jsonBody($request);
        $state = $body['state'] ?? null;

        if (!Validator::enum($state, $this->states)) {
            Response::validationError([['field' => 'state', 'message' => 'Invalid state.']]);
        }

        if (isset($body['notes']) && !Validator::maxLength($body['notes'], 2000)) {
            Response::validationError([['field' => 'notes', 'message' => 'Notes must be up to 2000 characters.']]);
        }

        $githubRepositoryId = (int) $params['githubRepositoryId'];
        $cached = RepositoryCache::findByGitHubRepositoryId($githubRepositoryId);
        $repository = $cached ? $cached['repository_data'] : [];
        $owner = $repository['owner']['login'] ?? ($repository['owner_login'] ?? ($cached['owner_login'] ?? ''));
        $name = $repository['name'] ?? ($cached['repository_name'] ?? '');

        $saved = UserRepositoryState::upsert(
            $user['id'],
            $githubRepositoryId,
            $owner,
            $name,
            $state,
            $body['notes'] ?? null
        );

        $eventType = $this->eventForState($state);
        $event = UserActivityEvent::create($user['id'], $eventType, $githubRepositoryId);
        (new BadgeEvaluatorService())->evaluateAfterActivityEvent($user['id'], $eventType, $event['id'] ?? null);
        Response::success(['state' => $saved]);
    }

    public function deleteState(Request $request, $params)
    {
        $user = $this->requireToken($request);
        UserRepositoryState::delete($user['id'], (int) $params['githubRepositoryId']);
        Response::success(['removed' => true]);
    }

    public function restore(Request $request, $params)
    {
        $user = $this->requireToken($request);
        $githubRepositoryId = (int) $params['githubRepositoryId'];
        $cached = RepositoryCache::findByGitHubRepositoryId($githubRepositoryId);
        $repository = $cached ? $cached['repository_data'] : [];
        $owner = $repository['owner']['login'] ?? ($repository['owner_login'] ?? ($cached['owner_login'] ?? ''));
        $name = $repository['name'] ?? ($cached['repository_name'] ?? '');

        $state = UserRepositoryState::upsert(
            $user['id'],
            $githubRepositoryId,
            $owner,
            $name,
            'saved',
            null
        );

        $event = UserActivityEvent::create($user['id'], 'restored_project', $githubRepositoryId);
        (new BadgeEvaluatorService())->evaluateAfterRepositoryStateChange($user['id'], $githubRepositoryId);
        Response::success(['state' => $state]);
    }

    private function eventForState($state)
    {
        $map = [
            'saved' => 'saved_project',
            'ignored' => 'ignored_project',
            'researching' => 'started_contributing',
            'working' => 'started_contributing',
            'pull_request_sent' => 'sent_pull_request',
            'contributed' => 'marked_contributed',
            'archived' => 'ignored_project',
        ];

        return $map[$state] ?? 'viewed_project';
    }
}
