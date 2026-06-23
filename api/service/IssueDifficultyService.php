<?php

class IssueDifficultyService
{
    public function estimate(array $issue)
    {
        $labels = array_map(function ($label) {
            return strtolower($label['name'] ?? '');
        }, $issue['labels'] ?? []);

        $title = strtolower($issue['title'] ?? '');
        $body = strtolower($issue['body'] ?? '');
        $text = $title . ' ' . $body . ' ' . implode(' ', $labels);
        $comments = (int) ($issue['comments'] ?? 0);

        $beginnerSignals = ['good first issue', 'beginner', 'documentation', 'translation', 'easy', 'starter', 'simple test'];
        $intermediateSignals = ['bug', 'feature', 'refactor', 'test', 'performance', 'accessibility'];
        $advancedSignals = ['architecture', 'security', 'breaking change', 'migration', 'infrastructure', 'complex'];

        $beginner = $this->countSignals($text, $beginnerSignals);
        $intermediate = $this->countSignals($text, $intermediateSignals);
        $advanced = $this->countSignals($text, $advancedSignals);

        $reasons = [];
        $level = 'intermediate';
        $confidence = 0.55;

        if ($advanced > 0 || $comments > 12) {
            $level = 'advanced';
            $confidence = 0.7;
            $reasons[] = 'advanced or high-discussion signals found';
        } elseif ($beginner > 0 && $comments <= 5) {
            $level = 'beginner';
            $confidence = 0.85;
            $reasons[] = 'beginner-friendly labels or wording found';
        } elseif ($intermediate > 0) {
            $level = 'intermediate';
            $confidence = 0.7;
            $reasons[] = 'implementation-oriented labels found';
        }

        if (in_array('good first issue', $labels, true)) {
            $reasons[] = 'good first issue label found';
        }

        if (in_array('help wanted', $labels, true)) {
            $reasons[] = 'help wanted label found';
        }

        if (!$reasons) {
            $reasons[] = 'estimated from title, labels and discussion size';
        }

        return [
            'level' => $level,
            'confidence' => $confidence,
            'reasons' => $reasons,
        ];
    }

    private function countSignals($text, array $signals)
    {
        $count = 0;
        foreach ($signals as $signal) {
            if (strpos($text, $signal) !== false) {
                $count++;
            }
        }
        return $count;
    }
}
