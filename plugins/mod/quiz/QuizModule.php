<?php

namespace MoodlePlugin\Mod\Quiz;

use App\Core\Plugin\Contracts\ModuleInterface;
use Illuminate\Support\Facades\DB;

/**
 * Quiz Activity Module
 */
class QuizModule implements ModuleInterface
{
    protected array $config = [];
    
    public function getName(): string
    {
        return 'quiz';
    }
    
    public function getType(): string
    {
        return 'mod';
    }
    
    public function getVersion(): string
    {
        return '2024012400';
    }
    
    public function getDescription(): string
    {
        return 'Interactive quiz activity with various question types and grading options.';
    }
    
    public function getIcon(): string
    {
        return 'fa-question-circle';
    }
    
    public function getSupportedFeatures(): array
    {
        return [
            'groups' => true,
            'groupings' => true,
            'groupmembersonly' => true,
            'idnumber' => true,
            'intro' => true,
            'completion' => true,
            'grade' => true,
            'gradebook' => true,
            'backup' => true,
            'restore' => true,
            'plagiarism' => false,
            'outcomes' => true,
        ];
    }
    
    public function getCapabilities(): array
    {
        return [
            'mod/quiz:view' => [
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['student' => CAP_ALLOW, 'teacher' => CAP_ALLOW],
            ],
            'mod/quiz:attempt' => [
                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['student' => CAP_ALLOW],
            ],
            'mod/quiz:manage' => [
                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['editingteacher' => CAP_ALLOW],
            ],
            'mod/quiz:grade' => [
                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW],
            ],
        ];
    }
    
    public function getDependencies(): array
    {
        return [
            'mod_question' => '2024011500',
            'core' => '2024011500'
        ];
    }
    
    public function isCompatible(): bool
    {
        // Check if questions table exists
        return \Schema::hasTable('mdl_question') && \Schema::hasTable('mdl_quiz');
    }
    
    public function getSettings(): array
    {
        return $this->config;
    }
    
    public function getConfigSchema(): array
    {
        return [
            'timeopen' => [
                'type' => 'datetime',
                'label' => 'Open the quiz',
                'help' => 'Date and time when the quiz becomes available',
                'default' => 0,
            ],
            'timeclose' => [
                'type' => 'datetime', 
                'label' => 'Close the quiz',
                'help' => 'Date and time when the quiz is no longer available',
                'default' => 0,
            ],
            'timelimit' => [
                'type' => 'duration',
                'label' => 'Time limit',
                'help' => 'Time allowed for the quiz in seconds',
                'default' => 0,
            ],
            'attempts' => [
                'type' => 'select',
                'label' => 'Attempts allowed',
                'options' => [1 => '1', 2 => '2', 3 => '3', 0 => 'Unlimited'],
                'default' => 0,
            ],
            'grademethod' => [
                'type' => 'select',
                'label' => 'Grading method',
                'options' => [
                    1 => 'Highest grade',
                    2 => 'Average grade',
                    3 => 'First attempt',
                    4 => 'Last attempt',
                ],
                'default' => 1,
            ],
        ];
    }
    
    public function addInstance(array $data): int
    {
        $quiz = [
            'course' => $data['course'],
            'name' => $data['name'],
            'intro' => $data['intro'] ?? '',
            'introformat' => $data['introformat'] ?? 1,
            'timeopen' => $data['timeopen'] ?? 0,
            'timeclose' => $data['timeclose'] ?? 0,
            'timelimit' => $data['timelimit'] ?? 0,
            'overduehandling' => $data['overduehandling'] ?? 'autosubmit',
            'graceperiod' => $data['graceperiod'] ?? 0,
            'preferredbehaviour' => $data['preferredbehaviour'] ?? 'deferredfeedback',
            'canredoquestions' => $data['canredoquestions'] ?? 0,
            'attempts' => $data['attempts'] ?? 0,
            'attemptonlast' => $data['attemptonlast'] ?? 0,
            'grademethod' => $data['grademethod'] ?? 1,
            'decimalpoints' => $data['decimalpoints'] ?? 2,
            'questiondecimalpoints' => $data['questiondecimalpoints'] ?? -1,
            'reviewattempt' => $data['reviewattempt'] ?? 0,
            'reviewcorrectness' => $data['reviewcorrectness'] ?? 0,
            'reviewmarks' => $data['reviewmarks'] ?? 0,
            'reviewspecificfeedback' => $data['reviewspecificfeedback'] ?? 0,
            'reviewgeneralfeedback' => $data['reviewgeneralfeedback'] ?? 0,
            'reviewrightanswer' => $data['reviewrightanswer'] ?? 0,
            'reviewoverallfeedback' => $data['reviewoverallfeedback'] ?? 0,
            'questionsperpage' => $data['questionsperpage'] ?? 1,
            'navmethod' => $data['navmethod'] ?? 'free',
            'shuffleanswers' => $data['shuffleanswers'] ?? 1,
            'sumgrades' => $data['sumgrades'] ?? 0,
            'grade' => $data['grade'] ?? 100,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        
        return DB::table('mdl_quiz')->insertGetId($quiz);
    }
    
    public function updateInstance(int $instanceId, array $data): bool
    {
        $updateData = array_intersect_key($data, array_flip([
            'name', 'intro', 'introformat', 'timeopen', 'timeclose', 
            'timelimit', 'attempts', 'grademethod', 'grade'
        ]));
        $updateData['timemodified'] = time();
        
        return DB::table('mdl_quiz')->where('id', $instanceId)->update($updateData) > 0;
    }
    
    public function deleteInstance(int $instanceId): bool
    {
        DB::beginTransaction();
        
        try {
            // Delete quiz attempts
            DB::table('mdl_quiz_attempts')->where('quiz', $instanceId)->delete();
            
            // Delete quiz grades  
            DB::table('mdl_quiz_grades')->where('quiz', $instanceId)->delete();
            
            // Delete the quiz
            DB::table('mdl_quiz')->where('id', $instanceId)->delete();
            
            DB::commit();
            return true;
            
        } catch (\Exception $e) {
            DB::rollback();
            return false;
        }
    }
    
    public function getView(int $instanceId, int $userId): string
    {
        $quiz = DB::table('mdl_quiz')->where('id', $instanceId)->first();
        
        if (!$quiz) {
            return '<div class="alert alert-danger">Quiz not found</div>';
        }
        
        // Get user's attempts
        $attempts = DB::table('mdl_quiz_attempts')
            ->where('quiz', $instanceId)
            ->where('userid', $userId)
            ->orderBy('attempt', 'desc')
            ->get();
        
        $canAttempt = $this->canAttemptQuiz($quiz, $attempts, $userId);
        
        $html = "<div class='quiz-view'>";
        $html .= "<h2>" . htmlspecialchars($quiz->name) . "</h2>";
        
        if ($quiz->intro) {
            $html .= "<div class='quiz-intro'>" . $quiz->intro . "</div>";
        }
        
        // Quiz information
        $html .= "<div class='quiz-info'>";
        
        if ($quiz->timeopen) {
            $html .= "<p><strong>Opens:</strong> " . date('Y-m-d H:i', $quiz->timeopen) . "</p>";
        }
        
        if ($quiz->timeclose) {
            $html .= "<p><strong>Closes:</strong> " . date('Y-m-d H:i', $quiz->timeclose) . "</p>";
        }
        
        if ($quiz->timelimit) {
            $html .= "<p><strong>Time limit:</strong> " . gmdate('H:i:s', $quiz->timelimit) . "</p>";
        }
        
        if ($quiz->attempts) {
            $html .= "<p><strong>Attempts allowed:</strong> " . $quiz->attempts . "</p>";
        }
        
        $html .= "</div>";
        
        // Attempt button or results
        if ($canAttempt) {
            $html .= "<div class='quiz-attempt'>";
            $html .= "<button class='btn btn-primary' onclick='startQuizAttempt({$instanceId})'>Start Quiz</button>";
            $html .= "</div>";
        } else {
            $html .= "<div class='quiz-completed'>";
            $html .= "<p>No more attempts allowed or quiz is closed.</p>";
        }
        
        // Previous attempts
        if (!$attempts->isEmpty()) {
            $html .= "<div class='quiz-attempts'>";
            $html .= "<h3>Previous Attempts</h3>";
            $html .= "<table class='table'>";
            $html .= "<tr><th>Attempt</th><th>State</th><th>Grade</th><th>Started</th><th>Finished</th></tr>";
            
            foreach ($attempts as $attempt) {
                $html .= "<tr>";
                $html .= "<td>{$attempt->attempt}</td>";
                $html .= "<td>" . ucfirst($attempt->state) . "</td>";
                $html .= "<td>" . ($attempt->sumgrades ?? '-') . " / {$quiz->sumgrades}</td>";
                $html .= "<td>" . date('Y-m-d H:i', $attempt->timestart) . "</td>";
                $html .= "<td>" . ($attempt->timefinish ? date('Y-m-d H:i', $attempt->timefinish) : '-') . "</td>";
                $html .= "</tr>";
            }
            
            $html .= "</table>";
            $html .= "</div>";
        }
        
        $html .= "</div>";
        
        return $html;
    }
    
    public function handleAjax(string $action, array $params): array
    {
        switch ($action) {
            case 'start_attempt':
                return $this->startAttempt($params);
                
            case 'save_answer':
                return $this->saveAnswer($params);
                
            case 'finish_attempt':
                return $this->finishAttempt($params);
                
            default:
                return ['success' => false, 'error' => 'Unknown action'];
        }
    }
    
    public function getGradingInfo(int $instanceId): array
    {
        $quiz = DB::table('mdl_quiz')->where('id', $instanceId)->first();
        
        return [
            'grade_type' => 1, // Point grade
            'grade_max' => $quiz->grade ?? 100,
            'grade_min' => 0,
            'grade_pass' => ($quiz->grade ?? 100) * 0.6, // 60% pass mark
        ];
    }
    
    protected function canAttemptQuiz($quiz, $attempts, int $userId): bool
    {
        // Check time restrictions
        $now = time();
        if ($quiz->timeopen && $now < $quiz->timeopen) {
            return false;
        }
        
        if ($quiz->timeclose && $now > $quiz->timeclose) {
            return false;
        }
        
        // Check attempt limits
        if ($quiz->attempts > 0 && $attempts->count() >= $quiz->attempts) {
            return false;
        }
        
        // Check if there's an in-progress attempt
        $inProgress = $attempts->where('state', 'inprogress')->first();
        if ($inProgress) {
            return false; // Can't start new attempt while one is in progress
        }
        
        return true;
    }
    
    protected function startAttempt(array $params): array
    {
        $quizId = $params['quiz_id'];
        $userId = $params['user_id'] ?? auth()->id();
        
        $quiz = DB::table('mdl_quiz')->where('id', $quizId)->first();
        if (!$quiz) {
            return ['success' => false, 'error' => 'Quiz not found'];
        }
        
        $attempts = DB::table('mdl_quiz_attempts')
            ->where('quiz', $quizId)
            ->where('userid', $userId)
            ->get();
        
        if (!$this->canAttemptQuiz($quiz, $attempts, $userId)) {
            return ['success' => false, 'error' => 'Cannot start new attempt'];
        }
        
        $attemptNumber = $attempts->count() + 1;
        
        $attemptId = DB::table('mdl_quiz_attempts')->insertGetId([
            'quiz' => $quizId,
            'userid' => $userId,
            'attempt' => $attemptNumber,
            'uniqueid' => $this->generateUniqueId(),
            'layout' => '1',
            'currentpage' => 0,
            'preview' => 0,
            'state' => 'inprogress',
            'timestart' => time(),
            'timefinish' => 0,
            'timemodified' => time(),
            'sumgrades' => null,
        ]);
        
        return [
            'success' => true,
            'attempt_id' => $attemptId,
            'attempt_number' => $attemptNumber,
            'time_limit' => $quiz->timelimit,
        ];
    }
    
    protected function saveAnswer(array $params): array
    {
        // Implementation would save student's answer to question
        return ['success' => true, 'saved' => true];
    }
    
    protected function finishAttempt(array $params): array
    {
        $attemptId = $params['attempt_id'];
        
        DB::table('mdl_quiz_attempts')
            ->where('id', $attemptId)
            ->update([
                'state' => 'finished',
                'timefinish' => time(),
                'timemodified' => time(),
            ]);
        
        return ['success' => true, 'finished' => true];
    }
    
    protected function generateUniqueId(): string
    {
        return uniqid('quiz_', true);
    }
    
    public function updateSettings(array $settings): bool
    {
        $this->config = array_merge($this->config, $settings);
        return true;
    }
    
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }
    
    public function init(): void
    {
        // Initialize quiz module
    }
    
    public function install(): bool
    {
        return true;
    }
    
    public function uninstall(): bool
    {
        return true;
    }
    
    public function upgrade(?string $oldVersion = null): bool
    {
        return true;
    }
    
    public function getInstance(int $instanceId): ?array
    {
        $quiz = DB::table('mdl_quiz')->where('id', $instanceId)->first();
        return $quiz ? (array) $quiz : null;
    }
    
    public function getEditForm(): string
    {
        return '<form class="quiz-edit-form">Quiz edit form would be here</form>';
    }
    
    public function processForm(array $data): bool
    {
        return true;
    }
    
    public function updateGrades(int $instanceId, array $grades): bool
    {
        foreach ($grades as $userId => $grade) {
            DB::table('mdl_quiz_grades')->updateOrInsert(
                ['quiz' => $instanceId, 'userid' => $userId],
                ['grade' => $grade, 'timemodified' => time()]
            );
        }
        return true;
    }
    
    public function getCompletionState(int $instanceId, int $userId): int
    {
        $attempt = DB::table('mdl_quiz_attempts')
            ->where('quiz', $instanceId)
            ->where('userid', $userId)
            ->where('state', 'finished')
            ->first();
        
        return $attempt ? 1 : 0;
    }
    
    public function getBackupData(int $instanceId): array
    {
        return ['instance_id' => $instanceId, 'type' => 'quiz'];
    }
    
    public function restoreFromBackup(array $data): int
    {
        return 0;
    }
    
    public function getDatabaseSchema(): array
    {
        return [
            'mdl_quiz' => [
                'fields' => ['id', 'course', 'name', 'intro', 'timeopen', 'timeclose'],
                'keys' => ['course'],
            ],
            'mdl_quiz_attempts' => [
                'fields' => ['id', 'quiz', 'userid', 'attempt', 'state'],
                'keys' => ['quiz', 'userid'],
            ],
        ];
    }
    
    public function getNavigationItems(int $instanceId): array
    {
        return [
            'view' => 'View Quiz',
            'attempt' => 'Attempt Quiz',
            'results' => 'View Results',
        ];
    }
    
    public function getRssFeed(int $instanceId): ?string
    {
        return null;
    }
    
    public function search(string $query, int $courseId = null): array
    {
        $results = [];
        $quizzes = DB::table('mdl_quiz')
            ->where('name', 'like', "%{$query}%")
            ->when($courseId, function($q, $courseId) {
                return $q->where('course', $courseId);
            })
            ->get();
            
        foreach ($quizzes as $quiz) {
            $results[] = [
                'title' => $quiz->name,
                'url' => "/mod/quiz/view/{$quiz->id}",
                'content' => $quiz->intro,
            ];
        }
        
        return $results;
    }
    
    public function getFileAreas(): array
    {
        return ['intro', 'question', 'feedback'];
    }
    
    public function processFiles(int $instanceId, array $files): bool
    {
        return true;
    }
}