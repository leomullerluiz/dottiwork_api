<?php

class TechnologyController extends BaseController
{
    private $proficiencyLevels = ['learning', 'basic', 'daily', 'advanced'];
    private $interestLevels = ['learn', 'contribute', 'mentor'];

    public function mine(Request $request)
    {
        $user = $this->requireToken($request);
        Response::success(['technologies' => UserTechnology::findByUserId($user['id'])]);
    }

    public function replace(Request $request)
    {
        $user = $this->requireToken($request);
        $body = $this->jsonBody($request);
        $items = isset($body['technologies']) && is_array($body['technologies']) ? $body['technologies'] : null;

        if ($items === null) {
            Response::validationError([['field' => 'technologies', 'message' => 'technologies must be a list.']]);
        }

        if (count($items) > 50) {
            Response::validationError([['field' => 'technologies', 'message' => 'Maximum of 50 technologies exceeded.']]);
        }

        $ids = [];
        $normalized = [];

        foreach ($items as $index => $item) {
            $technologyId = $item['technology_id'] ?? null;
            $errors = Validator::collectErrors([
                'technologies.' . $index . '.technology_id' => [
                    'technology_id must be an integer.' => Validator::integer($technologyId),
                ],
                'technologies.' . $index . '.proficiency_level' => [
                    'Invalid proficiency_level.' => isset($item['proficiency_level']) && Validator::enum($item['proficiency_level'], $this->proficiencyLevels),
                ],
                'technologies.' . $index . '.interest_level' => [
                    'Invalid interest_level.' => !isset($item['interest_level']) || Validator::enum($item['interest_level'], $this->interestLevels),
                ],
            ]);

            if ($errors) {
                Response::validationError($errors);
            }

            $id = (int) $technologyId;
            $ids[] = $id;
            $normalized[] = [
                'technology_id' => $id,
                'proficiency_level' => $item['proficiency_level'],
                'interest_level' => $item['interest_level'] ?? 'contribute',
            ];
        }

        if (!Validator::uniqueArray($ids)) {
            Response::validationError([['field' => 'technologies', 'message' => 'Do not send duplicate technologies.']]);
        }

        $active = Technology::findActiveByIds($ids);
        if (count($active) !== count($ids)) {
            Response::validationError([['field' => 'technologies', 'message' => 'One or more technologies do not exist or are inactive.']]);
        }

        $technologies = UserTechnology::replaceAll($user['id'], $normalized);
        (new BadgeEvaluatorService())->evaluateAfterProfileUpdate($user['id']);

        Response::success(['technologies' => $technologies]);
    }
}
