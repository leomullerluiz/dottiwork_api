<?php

class RepositoryController extends BaseController
{
    public function show(Request $request, $params)
    {
        $user = $this->requireToken($request);
        $owner = $params['owner'];
        $repo = $params['repo'];
        $cached = $this->loadRepositoryCache($user['id'], $owner, $repo);

        $repository = RepositorySummary::fromCacheRow(
            $cached,
            RepositoryIssueCache::statsByRepositoryId($cached['github_repository_id'])
        );
        $state = UserRepositoryState::findByUserAndRepository($user['id'], $cached['github_repository_id']);
        $match = UserRepositoryMatch::findByUserAndRepository($user['id'], $cached['github_repository_id']);

        UserActivityEvent::create($user['id'], 'viewed_project', $cached['github_repository_id'], [
            'owner' => $owner,
            'repo' => $repo,
        ]);

        Response::success([
            'repository' => $repository,
            'health' => $cached['health_data'],
            'user_state' => $state ? $state['state'] : null,
            'match' => $match ? $match['match'] : null,
        ]);
    }

    public function issues(Request $request, $params)
    {
        $user = $this->requireToken($request);
        $owner = $params['owner'];
        $repo = $params['repo'];
        $config = require __DIR__ . '/../config/github.php';
        $cachedRepo = $this->loadRepositoryCache($user['id'], $owner, $repo);

        $issues = RepositoryIssueCache::listFreshByRepositoryId($cachedRepo['github_repository_id'], [
            'limit' => $this->limit($request, 30, 100),
            'cursor' => $request->getQuery('cursor'),
        ]);

        if (!$issues) {
            try {
                $client = $this->githubClientForUser($user['id']);
                $rawIssues = $client->getRepositoryIssues($owner, $repo, [
                    'per_page' => $this->limit($request, 30, 100),
                ]);
                RepositoryIssueCache::upsertMany($cachedRepo['github_repository_id'], $rawIssues, $config['issues_cache_ttl_seconds'], new IssueDifficultyService());
                $issues = RepositoryIssueCache::listFreshByRepositoryId($cachedRepo['github_repository_id'], [
                    'limit' => $this->limit($request, 30, 100),
                ]);
            } catch (Exception $e) {
                Response::badGateway('Nao foi possivel carregar issues do GitHub.');
            }
        }

        Response::success([
            'items' => $this->filterIssues($issues, $request),
            'pagination' => ['next_cursor' => null],
        ]);
    }

    public function activity(Request $request, $params)
    {
        $user = $this->requireToken($request);
        $body = $this->jsonBody($request);
        $eventType = $body['event_type'] ?? null;
        $allowed = ['viewed_project', 'opened_github', 'started_contributing', 'sent_pull_request', 'marked_contributed'];

        if (!Validator::enum($eventType, $allowed)) {
            Response::validationError([['field' => 'event_type', 'message' => 'Tipo de evento invalido.']]);
        }

        $cachedRepo = RepositoryCache::findByOwnerRepo($params['owner'], $params['repo'], false);
        $githubRepositoryId = $cachedRepo ? $cachedRepo['github_repository_id'] : null;

        Response::created([
            'event' => UserActivityEvent::create($user['id'], $eventType, $githubRepositoryId, [
                'owner' => $params['owner'],
                'repo' => $params['repo'],
            ]),
        ]);
    }

    private function githubClientForUser($userId)
    {
        $account = OAuthAccount::findByUserAndProvider($userId, 'github');
        $token = $account ? OAuthAccount::decryptAccessToken($account) : null;
        return new GitHubClient($token);
    }

    private function loadRepositoryCache($userId, $owner, $repo)
    {
        $config = require __DIR__ . '/../config/github.php';
        $cached = RepositoryCache::findByOwnerRepo($owner, $repo, true);

        if ($cached) {
            return $cached;
        }

        try {
            $client = $this->githubClientForUser($userId);
            $repository = $client->getRepository($owner, $repo);
            $repository['languages'] = array_keys($client->getRepositoryLanguages($owner, $repo));
            $topics = $client->getRepositoryTopics($owner, $repo);
            $repository['topics'] = $topics['names'] ?? ($repository['topics'] ?? []);
            $labels = $client->getRepositoryLabels($owner, $repo);
            $contents = $client->getRepositoryContents($owner, $repo);
            $health = (new RepositoryHealthService())->analyze($repository, $labels, $contents);
            return RepositoryCache::upsert($repository, $health, $config['repository_cache_ttl_seconds']);
        } catch (Exception $e) {
            $cached = RepositoryCache::findByOwnerRepo($owner, $repo, false);
            if ($cached) {
                return $cached;
            }
            Response::badGateway('Nao foi possivel carregar repositorio do GitHub.');
        }
    }

    private function filterIssues(array $issues, Request $request)
    {
        $difficulty = $request->getQuery('difficulty');
        $label = $request->getQuery('label');

        return array_values(array_filter($issues, function ($item) use ($difficulty, $label) {
            if ($difficulty && (($item['difficulty_estimation']['level'] ?? null) !== $difficulty)) {
                return false;
            }

            if ($label) {
                $labels = array_map(function ($raw) {
                    return strtolower($raw['name'] ?? '');
                }, $item['issue_data']['labels'] ?? []);
                if (!in_array(strtolower($label), $labels, true)) {
                    return false;
                }
            }

            return true;
        }));
    }
}
