<?php

namespace Pannella\Cti\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Pannella\Cti\Tests\Fixtures\Assessment;
use Pannella\Cti\Tests\Fixtures\Quiz;
use Pannella\Cti\Tests\Fixtures\Survey;
use Pannella\Cti\Tests\Fixtures\QuizQuestion;
use Pannella\Cti\Tests\Fixtures\QuizAttempt;
use Pannella\Cti\Tests\Fixtures\MisconfiguredAssessment;
use Pannella\Cti\Tests\Fixtures\RegularModel;
use Pannella\Cti\Exceptions\SubtypeException;
use Illuminate\Database\Eloquent\Model;

class SubtypeModelTest extends TestCase
{
    protected $db;
    protected $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = new DB;
        $this->dispatcher = new Dispatcher(new Container);

        $this->db->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        //set up fresh event dispatcher
        $this->db->setEventDispatcher($this->dispatcher);
        Model::setEventDispatcher($this->dispatcher);

        $this->db->setAsGlobal();
        $this->db->bootEloquent();

        //force re-boot of models to ensure events are registered fresh
        Quiz::clearBootedModels();
        Survey::clearBootedModels();
        Assessment::clearBootedModels();

        //create test tables
        $this->createTables();
        $this->seedTestData();
    }

    protected function tearDown(): void
    {
        DB::statement('DROP TABLE IF EXISTS quiz_attempts');
        DB::statement('DROP TABLE IF EXISTS quiz_questions');
        DB::statement('DROP TABLE IF EXISTS assessment_survey');
        DB::statement('DROP TABLE IF EXISTS assessment_quiz');
        DB::statement('DROP TABLE IF EXISTS assessment');
        DB::statement('DROP TABLE IF EXISTS assessment_type');

        // clear all event listeners
        Quiz::clearBootedModels();
        Survey::clearBootedModels();
        Assessment::clearBootedModels();
        Model::unsetEventDispatcher();

        $this->db = null;
        $this->dispatcher = null;

        parent::tearDown();
    }

    protected function createTables(): void
    {
        DB::schema()->create('assessment_type', function (Blueprint $table) {
            $table->id();
            $table->string('label')->unique();
            $table->timestamps();
        });

        DB::schema()->create('assessment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_id')->constrained('assessment_type');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        DB::schema()->create('assessment_quiz', function (Blueprint $table) {
            $table->foreignId('assessment_id')->constrained('assessments')->onDelete('cascade');
            $table->integer('passing_score');
            $table->integer('time_limit')->nullable();
            $table->boolean('show_correct_answers')->default(false);
            $table->primary('assessment_id');
        });

        DB::schema()->create('assessment_survey', function (Blueprint $table) {
            $table->foreignId('assessment_id')->constrained('assessments')->onDelete('cascade');
            $table->boolean('anonymous')->default(false);
            $table->boolean('allow_multiple_responses')->default(false);
            $table->primary('assessment_id');
        });

        DB::schema()->create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id');
            $table->string('question_text');
            $table->integer('points')->default(1);
        });

        DB::schema()->create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id');
            $table->string('student_name');
            $table->integer('score');
        });
    }

    protected function seedTestData(): void
    {
        DB::table('assessment_type')->insert([
            ['id' => 1, 'label' => 'quiz'],
            ['id' => 2, 'label' => 'survey'],
        ]);
    }

    public function testCreateQuizWithOnlySubtypeAttributes(): void
    {
        $quiz = new Quiz();
        $quiz->passing_score = 80;
        $quiz->time_limit = 60;

        $saved = $quiz->save();

        $this->assertTrue($saved);

        $assessment = DB::table('assessment')->first();
        $this->assertNotNull($assessment);
        $this->assertEquals(1, $assessment->type_id);

        $quizData = DB::table('assessment_quiz')->first();
        $this->assertNotNull($quizData);
        $this->assertEquals(80, $quizData->passing_score);
        $this->assertEquals(60, $quizData->time_limit);

        $this->assertEquals($assessment->id, $quizData->assessment_id);
    }

    /**
     * Test creating a new quiz automatically sets the correct type_id
     */
    public function testCreateNewQuiz(): void
    {
        $quiz = new Quiz();
        $quiz->title = 'Test Quiz';
        $quiz->description = 'A test quiz';
        $quiz->passing_score = 80;
        $quiz->time_limit = 60;
        $quiz->show_correct_answers = true;

        $this->assertTrue($quiz->save());

        $assessment = DB::table('assessment')->first();
        $this->assertNotNull($assessment);
        $this->assertEquals('Test Quiz', $assessment->title);
        $this->assertEquals('A test quiz', $assessment->description);
        $this->assertEquals(1, $assessment->type_id);

        $quizData = DB::table('assessment_quiz')->first();
        $this->assertNotNull($quizData);
        $this->assertEquals(80, $quizData->passing_score);
        $this->assertEquals(60, $quizData->time_limit);
        $this->assertEquals(1, $quizData->show_correct_answers);

        $this->assertEquals($assessment->id, $quizData->assessment_id);
    }

    /**
     * Helper to create a quiz record directly in the database
     */
    protected function createQuizRecord(array $assessmentData = [], array $quizData = []): void
    {
        $defaultAssessment = [
            'id' => 1,
            'type_id' => 1,
            'title' => 'Test Quiz',
            'description' => 'Test Description',
            'enabled' => true
        ];

        $defaultQuiz = [
            'assessment_id' => 1,
            'passing_score' => 70,
            'time_limit' => 30,
            'show_correct_answers' => false
        ];

        DB::table('assessment')->insert(array_merge($defaultAssessment, $assessmentData));
        DB::table('assessment_quiz')->insert(array_merge($defaultQuiz, $quizData));
    }

    /**
     * Helper to create a survey record directly in the database
     */
    protected function createSurveyRecord(array $assessmentData = [], array $surveyData = []): void
    {
        $defaultAssessment = [
            'id' => 1,
            'type_id' => 2,
            'title' => 'Test Survey',
            'description' => 'Test Description',
            'enabled' => true
        ];

        $defaultSurvey = [
            'assessment_id' => 1,
            'anonymous' => false,
            'allow_multiple_responses' => false
        ];

        DB::table('assessment')->insert(array_merge($defaultAssessment, $assessmentData));
        DB::table('assessment_survey')->insert(array_merge($defaultSurvey, $surveyData));
    }

    public function testLoadExistingQuiz(): void
    {
        $this->createQuizRecord([
            'title' => 'Existing Quiz',
            'description' => 'An existing quiz'
        ], [
            'passing_score' => 70,
            'time_limit' => 30,
            'show_correct_answers' => false
        ]);

        $quiz = Quiz::find(1);

        $this->assertInstanceOf(Quiz::class, $quiz);
        $this->assertEquals('Existing Quiz', $quiz->title);
        $this->assertEquals(70, $quiz->passing_score);
        $this->assertEquals(30, $quiz->time_limit);
        $this->assertEquals(0, $quiz->show_correct_answers);
    }

    public function testUpdateExistingQuiz(): void
    {
        $this->createQuizRecord([
            'title' => 'Original Quiz',
            'description' => 'Original description'
        ], [
            'passing_score' => 70,
            'time_limit' => 30,
            'show_correct_answers' => false
        ]);

        $quiz = Quiz::find(1);
        $quiz->title = 'Updated Quiz';
        $quiz->passing_score = 90;
        $quiz->save();

        $assessment = DB::table('assessment')->first();
        $this->assertEquals('Updated Quiz', $assessment->title);

        $quizData = DB::table('assessment_quiz')->first();
        $this->assertEquals(90, $quizData->passing_score);
    }

    public function testDeleteQuiz(): void
    {
        $this->createQuizRecord([
            'title' => 'Quiz to Delete'
        ], [
            'passing_score' => 70
        ]);

        $quiz = Quiz::find(1);
        $this->assertTrue($quiz->delete());

        $this->assertNull(DB::table('assessment')->first());
        $this->assertNull(DB::table('assessment_quiz')->first());
    }

    /**
     * Test loading multiple quizzes with efficient subtype loading
     */
    public function testBulkLoadingSubtypes(): void
    {
        // Create multiple quizzes with unique IDs
        $this->createQuizRecord(
            [
                'id' => 1,
                'title' => 'Quiz 1',
                'type_id' => 1
            ],
            [
                'assessment_id' => 1,
                'passing_score' => 70
            ]
        );

        $this->createQuizRecord(
            [
                'id' => 2,
                'title' => 'Quiz 2',
                'type_id' => 1
            ],
            [
                'assessment_id' => 2,
                'passing_score' => 80
            ]
        );

        $this->createQuizRecord(
            [
                'id' => 3,
                'title' => 'Quiz 3',
                'type_id' => 1
            ],
            [
                'assessment_id' => 3,
                'passing_score' => 90
            ]
        );

        // Load all quizzes efficiently
        $quizzes = Quiz::all();

        $this->assertCount(3, $quizzes);
        $this->assertEquals('Quiz 1', $quizzes[0]->title);
        $this->assertEquals(70, $quizzes[0]->passing_score);
        $this->assertEquals('Quiz 2', $quizzes[1]->title);
        $this->assertEquals(80, $quizzes[1]->passing_score);
        $this->assertEquals('Quiz 3', $quizzes[2]->title);
        $this->assertEquals(90, $quizzes[2]->passing_score);
    }

    /**
     * Test replicating a quiz (clone with new ID)
     */
    public function testReplicateQuiz(): void
    {
        $this->createQuizRecord([
            'title' => 'Original Quiz',
            'description' => 'Original Description'
        ], [
            'passing_score' => 70,
            'time_limit' => 30
        ]);

        $original = Quiz::find(1);
        $clone = $original->replicate();

        $this->assertTrue($clone->save());

        // Verify clone has same attributes but different ID
        $this->assertNotEquals($original->id, $clone->id);
        $this->assertEquals($original->title, $clone->title);
        $this->assertEquals($original->passing_score, $clone->passing_score);
    }

    /**
     * Test refreshing a quiz from database
     */
    public function testRefreshQuiz(): void
    {
        $this->createQuizRecord([
            'title' => 'Test Quiz'
        ], [
            'passing_score' => 70
        ]);

        $quiz = Quiz::find(1);

        // Update database directly
        DB::table('assessment')->where('id', 1)->update(['title' => 'Updated Title']);
        DB::table('assessment_quiz')->where('assessment_id', 1)->update(['passing_score' => 90]);

        $quiz->refresh();

        $this->assertEquals('Updated Title', $quiz->title);
        $this->assertEquals(90, $quiz->passing_score);
    }

    /**
     * Test query builder with subtype conditions
     */
    public function testQueryBuilderWithSubtypeConditions(): void
    {
        $this->createQuizRecord(
            [
                'id' => 1,
                'type_id' => 1
            ],
            [
                'assessment_id' => 1,
                'passing_score' => 70
            ]
        );

        $this->createQuizRecord(
            [
                'id' => 2,
                'type_id' => 1
            ],
            [
                'assessment_id' => 2,
                'passing_score' => 80
            ]
        );

        $this->createQuizRecord(
            [
                'id' => 3,
                'type_id' => 1
            ],
            [
                'assessment_id' => 3,
                'passing_score' => 90
            ]
        );

        $highScoreQuizzes = Quiz::where('passing_score', '>', 75)->get();

        $this->assertCount(2, $highScoreQuizzes);
        $this->assertEquals([80, 90], $highScoreQuizzes->pluck('passing_score')->all());
    }

    /**
     * Test handling null values
     */
    public function testNullValues(): void
    {
        $quiz = new Quiz();
        $quiz->title = 'Test Quiz';
        $quiz->description = null;
        $quiz->time_limit = null;
        $quiz->passing_score = 70;

        $this->assertTrue($quiz->save());

        $loaded = Quiz::find($quiz->id);
        $this->assertNull($loaded->description);
        $this->assertNull($loaded->time_limit);
    }

    /**
     * Test CTI-specific events
     */
    public function testSubtypeEvents(): void
    {
        $events = [];

        Quiz::saved(function ($quiz) use (&$events) {
            $events[] = 'saved';
        });

        Quiz::subtypeSaved(function ($quiz) use (&$events) {
            $events[] = 'subtypeSaved';
        });

        $quiz = new Quiz();
        $quiz->title = 'Test Quiz';
        $quiz->passing_score = 70;
        $quiz->time_limit = 60;
        $quiz->save();

        $this->assertContains('saved', $events);
        $this->assertContains('subtypeSaved', $events);
    }

    /**
     * Test mass assignment
     */
    public function testMassAssignment(): void
    {
        $quiz = Quiz::create([
            'title' => 'Mass Assigned Quiz',
            'description' => 'Created via mass assignment',
            'passing_score' => 70,
            'time_limit' => 30,
            'show_correct_answers' => true
        ]);

        $this->assertNotNull($quiz->id);
        $this->assertEquals('Mass Assigned Quiz', $quiz->title);
        $this->assertEquals(70, $quiz->passing_score);
    }

    /**
     * Test that accessing undefined attributes returns null
     */
    public function testUndefinedAttributesReturnsNull(): void
    {
        $quiz = new Quiz();
        $this->assertNull($quiz->nonexistent_attribute);
    }

    /**
     * Test subtype casting with eager loading
     */
    public function testEagerLoading(): void
    {
        $this->createQuizRecord(
            [
                'id' => 1,
                'title' => 'Quiz 1',
                'type_id' => 1
            ],
            [
                'assessment_id' => 1,
                'passing_score' => 70
            ]
        );

        $this->createQuizRecord(
            [
                'id' => 2,
                'title' => 'Quiz 2',
                'type_id' => 1
            ],
            [
                'assessment_id' => 2,
                'passing_score' => 80
            ]
        );

        // Load multiple records at once
        $quizzes = Quiz::where('type_id', 1)->get();

        $this->assertCount(2, $quizzes);
        foreach ($quizzes as $quiz) {
            $this->assertInstanceOf(Quiz::class, $quiz);
            $this->assertTrue(isset($quiz->passing_score));
        }
    }

    /**
     * Test invalid type_id returns base Assessment (not morphed to a subtype)
     */
    public function testInvalidTypeId(): void
    {
        // Insert a type_id whose label isn't in the subtypeMap
        DB::table('assessment_type')->insert([
            'id' => 999,
            'label' => 'invalid_type'
        ]);

        DB::table('assessment')->insert([
            'id' => 1,
            'type_id' => 999,
            'title' => 'Invalid Assessment'
        ]);

        // Loading through the parent should return a base Assessment, not a subtype
        $assessment = Assessment::find(1);

        $this->assertInstanceOf(Assessment::class, $assessment);
        $this->assertNotInstanceOf(Quiz::class, $assessment);
        $this->assertNotInstanceOf(Survey::class, $assessment);
        $this->assertEquals('Invalid Assessment', $assessment->title);
    }

    /**
     * Test dirty attributes tracking
     */
    public function testDirtyAttributesTracking(): void
    {
        $quiz = new Quiz();

        // Set initial attributes
        $quiz->title = 'Test Quiz';
        $quiz->passing_score = 70;
        $this->assertTrue($quiz->isDirty());

        // Save to clear dirty state
        $quiz->save();
        $this->assertFalse($quiz->isDirty());

        // Modify only parent attribute
        $quiz->title = 'Updated Quiz';
        $this->assertTrue($quiz->isDirty('title'));
        $this->assertFalse($quiz->isDirty('passing_score'));

        // Modify only subtype attribute
        $quiz->title = 'Updated Quiz';  // Reset to remove dirty state
        $quiz->save();

        $quiz->passing_score = 80;
        $this->assertFalse($quiz->isDirty('title'));
        $this->assertTrue($quiz->isDirty('passing_score'));
    }

    /**
     * Test that primary keys are properly handled
     */
    public function testPrimaryKeyHandling(): void
    {
        $quiz = new Quiz();
        $quiz->title = 'Test Quiz';
        $quiz->passing_score = 70;
        $quiz->save();

        $this->assertNotNull($quiz->id);
        $this->assertEquals($quiz->id, $quiz->getKey());

        // Check that subtype table uses the same ID
        $subtypeRecord = DB::table('assessment_quiz')
            ->where('assessment_id', $quiz->id)
            ->first();
        $this->assertNotNull($subtypeRecord);
    }

    /**
     * Test that timestamps are properly handled
     */
    public function testTimestampHandling(): void
    {
        $quiz = new Quiz();
        $quiz->title = 'Test Quiz';
        $quiz->passing_score = 70;
        $quiz->save();

        $this->assertNotNull($quiz->created_at);
        $this->assertNotNull($quiz->updated_at);

        $originalUpdatedAt = $quiz->updated_at;
        sleep(1); // Ensure timestamp will be different

        $quiz->title = 'Updated Quiz';
        $quiz->save();

        $this->assertNotEquals($originalUpdatedAt, $quiz->updated_at);
    }

    /**
     * Test that fillable attributes are respected
     */
    public function testFillableProtection(): void
    {
        $data = [
            'title' => 'Test Quiz',
            'passing_score' => 70,
            'non_fillable_field' => 'should not be set'
        ];

        $quiz = new Quiz();
        $quiz->fill($data);

        $this->assertEquals('Test Quiz', $quiz->title);
        $this->assertEquals(70, $quiz->passing_score);
        $this->assertNull($quiz->non_fillable_field);
    }

    /**
     * Test mixed subtypes queried individually from the same database.
     * Verifies Quiz::all() and Survey::all() both load subtype data correctly
     * when both types exist in the same parent table.
     */
    public function testMixedSubtypesInDatabase(): void
    {
        // Create a quiz (type_id=1) and a survey (type_id=2) in the same parent table
        $this->createQuizRecord(
            ['id' => 1, 'title' => 'My Quiz', 'type_id' => 1],
            ['assessment_id' => 1, 'passing_score' => 80, 'time_limit' => 60]
        );

        $this->createSurveyRecord(
            ['id' => 2, 'title' => 'My Survey', 'type_id' => 2],
            ['assessment_id' => 2, 'anonymous' => true, 'allow_multiple_responses' => true]
        );

        // Query each subtype separately — they share the parent table
        $quizzes = Quiz::all();
        $this->assertCount(2, $quizzes);
        $quiz = $quizzes->first(fn ($q) => $q->title === 'My Quiz');
        $this->assertNotNull($quiz);
        $this->assertInstanceOf(Quiz::class, $quiz);
        $this->assertEquals(80, $quiz->passing_score);
        $this->assertEquals(60, $quiz->time_limit);

        $surveys = Survey::all();
        $this->assertCount(2, $surveys);
        $survey = $surveys->first(fn ($s) => $s->title === 'My Survey');
        $this->assertNotNull($survey);
        $this->assertInstanceOf(Survey::class, $survey);
        $this->assertEquals(1, $survey->anonymous);
        $this->assertEquals(1, $survey->allow_multiple_responses);

        // Parent query now returns morphed subtype instances (Quiz and Survey)
        $assessments = Assessment::all();
        $this->assertCount(2, $assessments);

        $quizAssessment = $assessments->first(fn ($a) => $a->title === 'My Quiz');
        $this->assertInstanceOf(Quiz::class, $quizAssessment);
        $this->assertEquals(80, $quizAssessment->passing_score);

        $surveyAssessment = $assessments->first(fn ($a) => $a->title === 'My Survey');
        $this->assertInstanceOf(Survey::class, $surveyAssessment);
        $this->assertTrue((bool) $surveyAssessment->anonymous);
    }

    /**
     * Test that missingConfiguration exception is thrown for missing subtypeKey.
     */
    public function testMissingConfigurationException(): void
    {
        $this->expectException(SubtypeException::class);
        $this->expectExceptionMessage('Missing CTI configuration property $subtypeKey');

        $model = new MisconfiguredAssessment();
        $model->getSubtypeKey();
    }

    /**
     * Test that typeResolutionFailed exception is thrown for unmapped label.
     */
    public function testTypeResolutionFailedException(): void
    {
        $this->expectException(SubtypeException::class);
        $this->expectExceptionMessage("Could not resolve type ID for label 'nonexistent' in table 'assessment_type'");

        throw SubtypeException::typeResolutionFailed('nonexistent', 'assessment_type');
    }

    /**
     * Test whereNotIn query builder with subtype columns.
     */
    public function testWhereNotInWithSubtypeColumn(): void
    {
        $this->createQuizRecord(
            ['id' => 1, 'type_id' => 1],
            ['assessment_id' => 1, 'passing_score' => 70]
        );
        $this->createQuizRecord(
            ['id' => 2, 'type_id' => 1],
            ['assessment_id' => 2, 'passing_score' => 80]
        );
        $this->createQuizRecord(
            ['id' => 3, 'type_id' => 1],
            ['assessment_id' => 3, 'passing_score' => 90]
        );

        $result = Quiz::whereNotIn('passing_score', [70, 90])->get();

        $this->assertCount(1, $result);
        $this->assertEquals(80, $result->first()->passing_score);
    }

    /**
     * Test whereBetween query builder with subtype columns.
     */
    public function testWhereBetweenWithSubtypeColumn(): void
    {
        $this->createQuizRecord(
            ['id' => 1, 'type_id' => 1],
            ['assessment_id' => 1, 'passing_score' => 60]
        );
        $this->createQuizRecord(
            ['id' => 2, 'type_id' => 1],
            ['assessment_id' => 2, 'passing_score' => 75]
        );
        $this->createQuizRecord(
            ['id' => 3, 'type_id' => 1],
            ['assessment_id' => 3, 'passing_score' => 95]
        );

        $result = Quiz::whereBetween('passing_score', [70, 90])->get();

        $this->assertCount(1, $result);
        $this->assertEquals(75, $result->first()->passing_score);
    }

    /**
     * Test creating a survey sets the correct type_id automatically.
     */
    public function testCreateNewSurvey(): void
    {
        $survey = new Survey();
        $survey->title = 'Test Survey';
        $survey->description = 'A test survey';
        $survey->anonymous = true;
        $survey->allow_multiple_responses = false;

        $this->assertTrue($survey->save());

        $assessment = DB::table('assessment')->first();
        $this->assertNotNull($assessment);
        $this->assertEquals('Test Survey', $assessment->title);
        $this->assertEquals(2, $assessment->type_id);

        $surveyData = DB::table('assessment_survey')->first();
        $this->assertNotNull($surveyData);
        $this->assertEquals(1, $surveyData->anonymous);
        $this->assertEquals(0, $surveyData->allow_multiple_responses);
        $this->assertEquals($assessment->id, $surveyData->assessment_id);
    }

    /**
     * Test orderBy with subtype columns.
     */
    public function testOrderByWithSubtypeColumn(): void
    {
        $this->createQuizRecord(['id' => 1, 'type_id' => 1], ['assessment_id' => 1, 'passing_score' => 90]);
        $this->createQuizRecord(['id' => 2, 'type_id' => 1], ['assessment_id' => 2, 'passing_score' => 70]);
        $this->createQuizRecord(['id' => 3, 'type_id' => 1], ['assessment_id' => 3, 'passing_score' => 80]);

        $quizzes = Quiz::orderBy('passing_score', 'asc')->get();

        $this->assertCount(3, $quizzes);
        $this->assertEquals([70, 80, 90], $quizzes->pluck('passing_score')->all());
    }

    /**
     * Test orderBy with subtype columns descending.
     */
    public function testOrderByDescWithSubtypeColumn(): void
    {
        $this->createQuizRecord(['id' => 1, 'type_id' => 1], ['assessment_id' => 1, 'passing_score' => 90]);
        $this->createQuizRecord(['id' => 2, 'type_id' => 1], ['assessment_id' => 2, 'passing_score' => 70]);
        $this->createQuizRecord(['id' => 3, 'type_id' => 1], ['assessment_id' => 3, 'passing_score' => 80]);

        $quizzes = Quiz::orderBy('passing_score', 'desc')->get();

        $this->assertCount(3, $quizzes);
        $this->assertEquals([90, 80, 70], $quizzes->pluck('passing_score')->all());
    }

    /**
     * Test groupBy with subtype columns.
     */
    public function testGroupByWithSubtypeColumn(): void
    {
        $this->createQuizRecord(['id' => 1, 'type_id' => 1], ['assessment_id' => 1, 'passing_score' => 80, 'show_correct_answers' => true]);
        $this->createQuizRecord(['id' => 2, 'type_id' => 1], ['assessment_id' => 2, 'passing_score' => 80, 'show_correct_answers' => true]);
        $this->createQuizRecord(['id' => 3, 'type_id' => 1], ['assessment_id' => 3, 'passing_score' => 90, 'show_correct_answers' => false]);

        $grouped = Quiz::selectRaw('passing_score, COUNT(*) as count')
            ->groupBy('passing_score')
            ->orderBy('passing_score')
            ->get();

        $this->assertCount(2, $grouped);
        $this->assertEquals(80, $grouped[0]->passing_score);
        $this->assertEquals(2, $grouped[0]->count);
        $this->assertEquals(90, $grouped[1]->passing_score);
        $this->assertEquals(1, $grouped[1]->count);
    }

    /**
     * Test having clause with subtype columns.
     */
    public function testHavingWithSubtypeColumn(): void
    {
        $this->createQuizRecord(['id' => 1, 'type_id' => 1], ['assessment_id' => 1, 'passing_score' => 80]);
        $this->createQuizRecord(['id' => 2, 'type_id' => 1], ['assessment_id' => 2, 'passing_score' => 80]);
        $this->createQuizRecord(['id' => 3, 'type_id' => 1], ['assessment_id' => 3, 'passing_score' => 90]);

        $result = Quiz::selectRaw('passing_score, COUNT(*) as count')
            ->groupBy('passing_score')
            ->having('passing_score', '>', 85)
            ->get();

        $this->assertCount(1, $result);
        $this->assertEquals(90, $result->first()->passing_score);
    }

    /**
     * Test aggregate count function with subtype columns.
     */
    public function testAggregateCountWithSubtypeColumn(): void
    {
        $this->createQuizRecord(['id' => 1, 'type_id' => 1], ['assessment_id' => 1, 'passing_score' => 80]);
        $this->createQuizRecord(['id' => 2, 'type_id' => 1], ['assessment_id' => 2, 'passing_score' => 90]);
        $this->createQuizRecord(['id' => 3, 'type_id' => 1], ['assessment_id' => 3, 'passing_score' => 70]);

        $count = Quiz::where('passing_score', '>=', 80)->count();

        $this->assertEquals(2, $count);
    }

    /**
     * Test aggregate sum function with subtype columns.
     */
    public function testAggregateSumWithSubtypeColumn(): void
    {
        $this->createQuizRecord(['id' => 1, 'type_id' => 1], ['assessment_id' => 1, 'passing_score' => 80]);
        $this->createQuizRecord(['id' => 2, 'type_id' => 1], ['assessment_id' => 2, 'passing_score' => 90]);
        $this->createQuizRecord(['id' => 3, 'type_id' => 1], ['assessment_id' => 3, 'passing_score' => 70]);

        // Aggregate functions on subtype columns require a where clause to trigger the join
        $sum = Quiz::where('passing_score', '>=', 0)->sum('passing_score');

        $this->assertEquals(240, $sum);
    }

    /**
     * Test aggregate avg function with subtype columns.
     */
    public function testAggregateAvgWithSubtypeColumn(): void
    {
        $this->createQuizRecord(['id' => 1, 'type_id' => 1], ['assessment_id' => 1, 'passing_score' => 80]);
        $this->createQuizRecord(['id' => 2, 'type_id' => 1], ['assessment_id' => 2, 'passing_score' => 90]);
        $this->createQuizRecord(['id' => 3, 'type_id' => 1], ['assessment_id' => 3, 'passing_score' => 70]);

        // Aggregate functions on subtype columns require a where clause to trigger the join
        $avg = Quiz::where('passing_score', '>=', 0)->avg('passing_score');

        $this->assertEquals(80, $avg);
    }

    /**
     * Test aggregate max function with subtype columns.
     */
    public function testAggregateMaxWithSubtypeColumn(): void
    {
        $this->createQuizRecord(['id' => 1, 'type_id' => 1], ['assessment_id' => 1, 'passing_score' => 80]);
        $this->createQuizRecord(['id' => 2, 'type_id' => 1], ['assessment_id' => 2, 'passing_score' => 90]);
        $this->createQuizRecord(['id' => 3, 'type_id' => 1], ['assessment_id' => 3, 'passing_score' => 70]);

        // Aggregate functions on subtype columns require a where clause to trigger the join
        $max = Quiz::where('passing_score', '>=', 0)->max('passing_score');

        $this->assertEquals(90, $max);
    }

    /**
     * Test aggregate min function with subtype columns.
     */
    public function testAggregateMinWithSubtypeColumn(): void
    {
        $this->createQuizRecord(['id' => 1, 'type_id' => 1], ['assessment_id' => 1, 'passing_score' => 80]);
        $this->createQuizRecord(['id' => 2, 'type_id' => 1], ['assessment_id' => 2, 'passing_score' => 90]);
        $this->createQuizRecord(['id' => 3, 'type_id' => 1], ['assessment_id' => 3, 'passing_score' => 70]);

        // Aggregate functions on subtype columns require a where clause to trigger the join
        $min = Quiz::where('passing_score', '>=', 0)->min('passing_score');

        $this->assertEquals(70, $min);
    }

    /**
     * Test select with specific subtype columns.
     */
    public function testSelectWithSubtypeColumns(): void
    {
        $this->createQuizRecord(
            ['id' => 1, 'title' => 'Test Quiz', 'type_id' => 1],
            ['assessment_id' => 1, 'passing_score' => 80, 'time_limit' => 60]
        );

        $quiz = Quiz::select('passing_score')->first();

        $this->assertEquals(80, $quiz->passing_score);
        // Title should still be accessible even though not explicitly selected
        // because we're loading the full model
    }

    /**
     * Test subtypeHasMany relationship.
     */
    public function testSubtypeHasManyRelationship(): void
    {
        $this->createQuizRecord(['id' => 1, 'title' => 'Test Quiz', 'type_id' => 1], ['assessment_id' => 1, 'passing_score' => 80]);

        DB::table('quiz_questions')->insert([
            ['assessment_id' => 1, 'question_text' => 'Question 1', 'points' => 10],
            ['assessment_id' => 1, 'question_text' => 'Question 2', 'points' => 15],
        ]);

        $quiz = Quiz::find(1);
        $questions = $quiz->questions;

        $this->assertCount(2, $questions);
        $this->assertEquals('Question 1', $questions[0]->question_text);
        $this->assertEquals('Question 2', $questions[1]->question_text);
    }

    /**
     * Test subtypeHasMany relationship with filtering.
     */
    public function testSubtypeHasManyWithFiltering(): void
    {
        $this->createQuizRecord(['id' => 1, 'title' => 'Test Quiz', 'type_id' => 1], ['assessment_id' => 1, 'passing_score' => 80]);

        DB::table('quiz_questions')->insert([
            ['assessment_id' => 1, 'question_text' => 'Easy Question', 'points' => 5],
            ['assessment_id' => 1, 'question_text' => 'Hard Question', 'points' => 20],
        ]);

        $quiz = Quiz::find(1);
        $hardQuestions = $quiz->questions()->where('points', '>', 10)->get();

        $this->assertCount(1, $hardQuestions);
        $this->assertEquals('Hard Question', $hardQuestions[0]->question_text);
    }

    /**
     * Test subtypeHasMany relationship with multiple subtype instances.
     */
    public function testSubtypeHasManyWithMultipleInstances(): void
    {
        $this->createQuizRecord(['id' => 1, 'title' => 'Quiz 1', 'type_id' => 1], ['assessment_id' => 1, 'passing_score' => 80]);
        $this->createQuizRecord(['id' => 2, 'title' => 'Quiz 2', 'type_id' => 1], ['assessment_id' => 2, 'passing_score' => 90]);

        DB::table('quiz_questions')->insert([
            ['assessment_id' => 1, 'question_text' => 'Q1 Question 1', 'points' => 10],
            ['assessment_id' => 2, 'question_text' => 'Q2 Question 1', 'points' => 15],
            ['assessment_id' => 2, 'question_text' => 'Q2 Question 2', 'points' => 20],
        ]);

        $quiz1 = Quiz::find(1);
        $quiz2 = Quiz::find(2);

        $this->assertCount(1, $quiz1->questions);
        $this->assertCount(2, $quiz2->questions);
        $this->assertEquals('Q1 Question 1', $quiz1->questions[0]->question_text);
        $this->assertEquals('Q2 Question 1', $quiz2->questions[0]->question_text);
    }

    /**
     * Test creating related models through subtypeHasMany relationship.
     */
    public function testSubtypeHasManyCreate(): void
    {
        $this->createQuizRecord(['id' => 1, 'title' => 'Test Quiz', 'type_id' => 1], ['assessment_id' => 1, 'passing_score' => 80]);

        $quiz = Quiz::find(1);
        $question = $quiz->questions()->create([
            'question_text' => 'New Question',
            'points' => 25
        ]);

        $this->assertNotNull($question->id);
        $this->assertEquals(1, $question->assessment_id);
        $this->assertEquals('New Question', $question->question_text);

        // Verify it's in the database
        $this->assertEquals(1, DB::table('quiz_questions')->where('assessment_id', 1)->count());
    }

    /**
     * Test subtypeHasMany relationship count.
     */
    public function testSubtypeHasManyCount(): void
    {
        $this->createQuizRecord(['id' => 1, 'title' => 'Test Quiz', 'type_id' => 1], ['assessment_id' => 1, 'passing_score' => 80]);

        DB::table('quiz_questions')->insert([
            ['assessment_id' => 1, 'question_text' => 'Question 1', 'points' => 10],
            ['assessment_id' => 1, 'question_text' => 'Question 2', 'points' => 15],
            ['assessment_id' => 1, 'question_text' => 'Question 3', 'points' => 20],
        ]);

        $quiz = Quiz::find(1);
        $count = $quiz->questions()->count();

        $this->assertEquals(3, $count);
    }

    /**
     * Test subtypeSaving event can halt save operation.
     */
    public function testSubtypeSavingEventCanHalt(): void
    {
        $eventFired = false;

        Quiz::subtypeSaving(function ($quiz) use (&$eventFired) {
            $eventFired = true;
            // Return false to halt the save
            return false;
        });

        $quiz = new Quiz();
        $quiz->title = 'Test Quiz';
        $quiz->passing_score = 70;

        $result = $quiz->save();

        $this->assertTrue($eventFired);
        $this->assertFalse($result);
        $this->assertNull($quiz->id); // Should not have been saved
    }

    /**
     * Test subtypeSaving event allows save when returning true.
     */
    public function testSubtypeSavingEventAllowsSave(): void
    {
        $eventFired = false;

        Quiz::subtypeSaving(function ($quiz) use (&$eventFired) {
            $eventFired = true;
            return true;
        });

        $quiz = new Quiz();
        $quiz->title = 'Test Quiz';
        $quiz->passing_score = 70;

        $result = $quiz->save();

        $this->assertTrue($eventFired);
        $this->assertTrue($result);
        $this->assertNotNull($quiz->id);
    }

    /**
     * Test subtypeDeleting event can halt delete operation.
     */
    public function testSubtypeDeletingEventCanHalt(): void
    {
        $this->createQuizRecord(['id' => 1, 'title' => 'Test Quiz', 'type_id' => 1], ['assessment_id' => 1, 'passing_score' => 80]);

        $eventFired = false;

        Quiz::subtypeDeleting(function ($quiz) use (&$eventFired) {
            $eventFired = true;
            // Return false to halt the delete
            return false;
        });

        $quiz = Quiz::find(1);
        $result = $quiz->delete();

        $this->assertTrue($eventFired);
        $this->assertFalse($result);

        // Verify record still exists
        $this->assertNotNull(DB::table('assessment')->where('id', 1)->first());
        $this->assertNotNull(DB::table('assessment_quiz')->where('assessment_id', 1)->first());
    }

    /**
     * Test subtypeDeleting event allows delete when returning true.
     */
    public function testSubtypeDeletingEventAllowsDelete(): void
    {
        $this->createQuizRecord(['id' => 1, 'title' => 'Test Quiz', 'type_id' => 1], ['assessment_id' => 1, 'passing_score' => 80]);

        $eventFired = false;

        Quiz::subtypeDeleting(function ($quiz) use (&$eventFired) {
            $eventFired = true;
            return true;
        });

        $quiz = Quiz::find(1);
        $result = $quiz->delete();

        $this->assertTrue($eventFired);
        $this->assertTrue($result);

        // Verify record was deleted
        $this->assertNull(DB::table('assessment')->where('id', 1)->first());
        $this->assertNull(DB::table('assessment_quiz')->where('assessment_id', 1)->first());
    }

    /**
     * Test subtypeDeleted event fires after successful delete.
     */
    public function testSubtypeDeletedEventFires(): void
    {
        $this->createQuizRecord(['id' => 1, 'title' => 'Test Quiz', 'type_id' => 1], ['assessment_id' => 1, 'passing_score' => 80]);

        $eventFired = false;
        $deletedQuizId = null;

        Quiz::subtypeDeleted(function ($quiz) use (&$eventFired, &$deletedQuizId) {
            $eventFired = true;
            $deletedQuizId = $quiz->id;
        });

        $quiz = Quiz::find(1);
        $quiz->delete();

        $this->assertTrue($eventFired);
        $this->assertEquals(1, $deletedQuizId);
    }

    /**
     * Test subtypeSaved event fires after successful save.
     */
    public function testSubtypeSavedEventFires(): void
    {
        $eventFired = false;
        $savedQuiz = null;

        Quiz::subtypeSaved(function ($quiz) use (&$eventFired, &$savedQuiz) {
            $eventFired = true;
            $savedQuiz = $quiz;
        });

        $quiz = new Quiz();
        $quiz->title = 'Test Quiz';
        $quiz->passing_score = 70;
        $quiz->save();

        $this->assertTrue($eventFired);
        $this->assertNotNull($savedQuiz);
        $this->assertEquals('Test Quiz', $savedQuiz->title);
        $this->assertEquals(70, $savedQuiz->passing_score);
    }

    /**
     * Test subtypeSaving event receives model with correct data.
     */
    public function testSubtypeSavingEventReceivesCorrectData(): void
    {
        $receivedQuiz = null;

        Quiz::subtypeSaving(function ($quiz) use (&$receivedQuiz) {
            $receivedQuiz = $quiz;
        });

        $quiz = new Quiz();
        $quiz->title = 'Test Quiz';
        $quiz->passing_score = 85;
        $quiz->time_limit = 120;
        $quiz->save();

        $this->assertNotNull($receivedQuiz);
        $this->assertEquals('Test Quiz', $receivedQuiz->title);
        $this->assertEquals(85, $receivedQuiz->passing_score);
        $this->assertEquals(120, $receivedQuiz->time_limit);
    }

    /**
     * Test SubtypedCollection with empty collection.
     */
    public function testSubtypedCollectionWithEmptyCollection(): void
    {
        $collection = new \Pannella\Cti\Support\SubtypedCollection([]);
        
        $this->assertCount(0, $collection);
        $this->assertTrue($collection->isEmpty());
    }

    /**
     * Test SubtypedCollection with models that have no subtype table.
     */
    public function testSubtypedCollectionWithNoSubtypeTable(): void
    {
        DB::table('assessment_type')->insert(['id' => 3, 'label' => 'regular']);
        DB::table('assessment')->insert([
            'id' => 1,
            'type_id' => 3,
            'title' => 'Regular Assessment',
            'enabled' => true
        ]);

        $model = RegularModel::find(1);
        $collection = new \Pannella\Cti\Support\SubtypedCollection([$model]);

        $this->assertCount(1, $collection);
        $this->assertEquals('Regular Assessment', $collection->first()->title);
    }

    /**
     * Test SubtypedCollection with models missing primary keys.
     */
    public function testSubtypedCollectionWithModelsWithoutKeys(): void
    {
        $model = new Quiz();
        $model->title = 'Unsaved Quiz';
        $model->passing_score = 70;
        // Note: Not saved, so no primary key

        $collection = new \Pannella\Cti\Support\SubtypedCollection([$model]);

        $this->assertCount(1, $collection);
        $this->assertNull($collection->first()->id);
    }

    /**
     * Test model without subtypeTable behaves like normal model.
     */
    public function testModelWithoutSubtypeTableActsLikeNormalModel(): void
    {
        DB::table('assessment_type')->insert(['id' => 3, 'label' => 'regular']);
        
        $model = new RegularModel();
        $model->type_id = 3;
        $model->title = 'Regular Model Test';
        $model->description = 'Testing regular model behavior';
        $model->enabled = true;

        $result = $model->save();

        $this->assertTrue($result);
        $this->assertNotNull($model->id);

        // Verify only parent table has data
        $assessmentRecord = DB::table('assessment')->where('id', $model->id)->first();
        $this->assertNotNull($assessmentRecord);
        $this->assertEquals('Regular Model Test', $assessmentRecord->title);
    }

    /**
     * Test model without subtypeAttributes behaves like normal model.
     */
    public function testModelWithoutSubtypeAttributesActsLikeNormalModel(): void
    {
        DB::table('assessment_type')->insert(['id' => 3, 'label' => 'regular']);
        DB::table('assessment')->insert([
            'id' => 1,
            'type_id' => 3,
            'title' => 'Test',
            'enabled' => true
        ]);

        $model = RegularModel::find(1);
        $model->title = 'Updated Test';
        $result = $model->save();

        $this->assertTrue($result);
        
        $assessmentRecord = DB::table('assessment')->where('id', 1)->first();
        $this->assertEquals('Updated Test', $assessmentRecord->title);
    }

    /**
     * Test loadSubtypeData when subtype record doesn't exist (should not throw exception).
     */
    public function testLoadSubtypeDataWhenSubtypeRecordMissing(): void
    {
        // Create parent record but no subtype record
        DB::table('assessment')->insert([
            'id' => 1,
            'type_id' => 1,
            'title' => 'Quiz Without Subtype Data',
            'enabled' => true
        ]);

        $quiz = Quiz::find(1);

        // Should not throw exception, but subtype fields should be null
        $this->assertNotNull($quiz);
        $this->assertEquals('Quiz Without Subtype Data', $quiz->title);
        $this->assertNull($quiz->passing_score);
        $this->assertNull($quiz->time_limit);
    }

    /**
     * Test save with only subtype attributes modified.
     */
    public function testSaveWithOnlySubtypeAttributesModified(): void
    {
        $this->createQuizRecord(
            ['id' => 1, 'title' => 'Original Title', 'type_id' => 1],
            ['assessment_id' => 1, 'passing_score' => 70, 'time_limit' => 30]
        );

        $quiz = Quiz::find(1);
        
        // Modify only subtype attribute
        $quiz->passing_score = 85;
        $result = $quiz->save();

        $this->assertTrue($result);

        // Verify parent wasn't unnecessarily modified (except updated_at)
        $assessmentRecord = DB::table('assessment')->where('id', 1)->first();
        $this->assertEquals('Original Title', $assessmentRecord->title);

        // Verify subtype was updated
        $quizRecord = DB::table('assessment_quiz')->where('assessment_id', 1)->first();
        $this->assertEquals(85, $quizRecord->passing_score);
    }

    /**
     * Test save with only parent attributes modified.
     */
    public function testSaveWithOnlyParentAttributesModified(): void
    {
        $this->createQuizRecord(
            ['id' => 1, 'title' => 'Original Title', 'type_id' => 1],
            ['assessment_id' => 1, 'passing_score' => 70, 'time_limit' => 30]
        );

        $quiz = Quiz::find(1);
        
        // Modify only parent attribute
        $quiz->title = 'Updated Title';
        $result = $quiz->save();

        $this->assertTrue($result);

        // Verify parent was updated
        $assessmentRecord = DB::table('assessment')->where('id', 1)->first();
        $this->assertEquals('Updated Title', $assessmentRecord->title);

        // Verify subtype wasn't modified
        $quizRecord = DB::table('assessment_quiz')->where('assessment_id', 1)->first();
        $this->assertEquals(70, $quizRecord->passing_score);
        $this->assertEquals(30, $quizRecord->time_limit);
    }

    /**
     * Test pagination with subtype data loading.
     * Note: Pagination requires illuminate/pagination package which may not be installed.
     */
    public function testPaginationWithSubtypeData(): void
    {
        if (!class_exists('\Illuminate\Pagination\Paginator')) {
            $this->markTestSkipped('Pagination package not installed');
        }

        // Create 15 quiz records
        for ($i = 1; $i <= 15; $i++) {
            $this->createQuizRecord(
                ['id' => $i, 'title' => "Quiz $i", 'type_id' => 1],
                ['assessment_id' => $i, 'passing_score' => 60 + $i]
            );
        }

        $paginated = Quiz::paginate(5);

        $this->assertCount(5, $paginated);
        $this->assertEquals(15, $paginated->total());
        $this->assertEquals(3, $paginated->lastPage());

        // Verify first page items have subtype data loaded
        foreach ($paginated as $quiz) {
            $this->assertNotNull($quiz->passing_score);
            $this->assertGreaterThan(60, $quiz->passing_score);
        }
    }

    /**
     * Test cache behavior in resolveSubtypeLabel.
     */
    public function testResolveSubtypeLabelCaching(): void
    {
        // First call should hit the database
        $label1 = Assessment::resolveSubtypeLabel(1);
        $this->assertEquals('quiz', $label1);

        // Second call should use cache (same label for same ID)
        $label2 = Assessment::resolveSubtypeLabel(1);
        $this->assertEquals('quiz', $label2);

        // Different ID should return different label
        $label3 = Assessment::resolveSubtypeLabel(2);
        $this->assertEquals('survey', $label3);
    }

    /**
     * Test querying with table-qualified column names.
     */
    public function testQueryWithTableQualifiedColumnNames(): void
    {
        $this->createQuizRecord(
            ['id' => 1, 'title' => 'Quiz 1', 'type_id' => 1],
            ['assessment_id' => 1, 'passing_score' => 80]
        );
        $this->createQuizRecord(
            ['id' => 2, 'title' => 'Quiz 2', 'type_id' => 1],
            ['assessment_id' => 2, 'passing_score' => 90]
        );

        // Query using table-qualified column name
        $quizzes = Quiz::where('assessment_quiz.passing_score', '>=', 85)->get();

        $this->assertCount(1, $quizzes);
        $this->assertEquals('Quiz 2', $quizzes->first()->title);
        $this->assertEquals(90, $quizzes->first()->passing_score);
    }

    /**
     * Test parent model morphs to subtype via newFromBuilder.
     */
    public function testParentModelMorphsToSubtype(): void
    {
        $this->createQuizRecord(
            ['id' => 1, 'title' => 'Test Quiz', 'type_id' => 1],
            ['assessment_id' => 1, 'passing_score' => 80]
        );
        $this->createSurveyRecord(
            ['id' => 2, 'title' => 'Test Survey', 'type_id' => 2],
            ['assessment_id' => 2, 'anonymous' => true]
        );

        // Load through parent model - should morph to correct subtypes
        $assessments = Assessment::orderBy('id')->get();

        $this->assertCount(2, $assessments);
        
        // The first assessment (id=1, type_id=1) should be morphed to Quiz
        $quiz = $assessments[0];
        $this->assertInstanceOf(Quiz::class, $quiz);
        $this->assertEquals('Test Quiz', $quiz->title);
        $this->assertEquals(80, $quiz->passing_score);
        
        // The second assessment (id=2, type_id=2) should be morphed to Survey
        $survey = $assessments[1];
        $this->assertInstanceOf(Survey::class, $survey);
        $this->assertEquals('Test Survey', $survey->title);
        $this->assertTrue((bool) $survey->anonymous);
    }

    /**
     * Test parent model find() morphs to correct subtype.
     */
    public function testParentModelFindMorphsToSubtype(): void
    {
        $this->createQuizRecord(
            ['id' => 1, 'title' => 'Test Quiz', 'type_id' => 1],
            ['assessment_id' => 1, 'passing_score' => 80]
        );

        // Find through parent model - should return morphed Quiz instance
        $assessment = Assessment::find(1);

        $this->assertInstanceOf(Quiz::class, $assessment);
        $this->assertEquals('Test Quiz', $assessment->title);
        $this->assertEquals(80, $assessment->passing_score);
    }

    /**
     * Test newModelInstance creates instance of correct class.
     */
    public function testNewModelInstance(): void
    {
        $quiz = new Quiz();
        
        // newModelInstance should create a new instance of the same class
        $instance = $quiz->newModelInstance(['title' => 'New Quiz']);

        $this->assertInstanceOf(Quiz::class, $instance);
        $this->assertEquals('New Quiz', $instance->title);
        $this->assertNull($instance->id); // Not saved yet
    }

    /**
     * Test getSubtypeLabel on base Assessment model (when morphing is disabled).
     */
    public function testGetSubtypeLabelOnBaseAssessmentModel(): void
    {
        // Insert an assessment with a type not in the subtype map to prevent morphing
        DB::table('assessment_type')->insert(['id' => 99, 'label' => 'unmapped_type']);
        DB::table('assessment')->insert([
            'id' => 1,
            'type_id' => 99,
            'title' => 'Unmapped Assessment',
            'enabled' => true
        ]);

        // This should return base Assessment instance (no morphing)
        $assessment = Assessment::find(1);
        $this->assertInstanceOf(Assessment::class, $assessment);
        $this->assertNotInstanceOf(Quiz::class, $assessment);
        
        // getSubtypeLabel should work on base Assessment instances
        $label = $assessment->getSubtypeLabel();
        $this->assertEquals('unmapped_type', $label);
    }

    /**
     * Test getSubtypeMap returns correct mapping.
     */
    public function testGetSubtypeMap(): void
    {
        $assessment = new Assessment();
        $map = $assessment->getSubtypeMap();

        $this->assertIsArray($map);
        $this->assertArrayHasKey('quiz', $map);
        $this->assertArrayHasKey('survey', $map);
        $this->assertEquals(Quiz::class, $map['quiz']);
        $this->assertEquals(Survey::class, $map['survey']);
    }
}