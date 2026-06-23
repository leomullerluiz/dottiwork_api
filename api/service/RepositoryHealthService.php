<?php

class RepositoryHealthService
{
    public function analyze(array $repository, array $labels = [], array $contents = [])
    {
        $labelNames = array_map(function ($label) {
            return strtolower($label['name'] ?? '');
        }, $labels);

        $contentNames = array_map(function ($item) {
            return strtolower($item['name'] ?? '');
        }, is_array($contents) ? $contents : []);

        $hasReadme = $this->containsPrefix($contentNames, 'readme');
        $hasContributing = $this->containsPrefix($contentNames, 'contributing');
        $hasCodeOfConduct = $this->containsPrefix($contentNames, 'code_of_conduct') || $this->containsPrefix($contentNames, 'code-of-conduct');
        $hasCi = in_array('.github', $contentNames, true) || in_array('.gitlab-ci.yml', $contentNames, true);
        $hasTests = $this->containsPrefix($contentNames, 'tests') || $this->containsPrefix($contentNames, '__tests__');
        $hasContributionLabels = in_array('good first issue', $labelNames, true) || in_array('help wanted', $labelNames, true);

        $score = 0;
        $score += $hasReadme ? 15 : 0;
        $score += $hasContributing ? 20 : 0;
        $score += $hasCodeOfConduct ? 10 : 0;
        $score += !empty($repository['license']) ? 10 : 0;
        $score += $hasCi ? 10 : 0;
        $score += $hasTests ? 10 : 0;
        $score += $hasContributionLabels ? 15 : 0;
        $score += !empty($repository['description']) ? 10 : 0;

        return [
            'score' => min($score, 100),
            'has_readme' => $hasReadme,
            'has_contributing' => $hasContributing,
            'has_code_of_conduct' => $hasCodeOfConduct,
            'has_ci' => $hasCi,
            'has_tests' => $hasTests,
            'has_contribution_labels' => $hasContributionLabels,
        ];
    }

    private function containsPrefix(array $names, $prefix)
    {
        foreach ($names as $name) {
            if (strpos($name, $prefix) === 0) {
                return true;
            }
        }
        return false;
    }
}
