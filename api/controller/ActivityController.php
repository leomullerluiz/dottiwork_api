<?php

class ActivityController extends BaseController
{
    public function history(Request $request)
    {
        $user = $this->requireToken($request);
        $eventType = $request->getQuery('event_type');
        if ($eventType && !Validator::enum($eventType, UserActivityEvent::$allowedTypes)) {
            Response::validationError([['field' => 'event_type', 'message' => 'Invalid event type.']]);
        }

        Response::success([
            'items' => UserActivityEvent::listByUser($user['id'], [
                'event_type' => $eventType,
                'github_repository_id' => $request->getQuery('github_repository_id'),
                'limit' => $this->limit($request, 50, 100),
                'cursor' => $request->getQuery('cursor'),
            ]),
            'pagination' => ['next_cursor' => null],
        ]);
    }

    public function clearHistory(Request $request)
    {
        $user = $this->requireToken($request);
        $deleted = UserActivityEvent::deleteByUser($user['id']);
        Response::success(['deleted' => $deleted]);
    }
}
