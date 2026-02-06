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
use Pannella\Cti\Tests\Fixtures\MisconfiguredAssessment;
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

        // Parent query returns base Assessment instances that resolve subtype labels
        $assessments = Assessment::all();
        $this->assertCount(2, $assessments);

        $quizAssessment = $assessments->first(fn ($a) => $a->title === 'My Quiz');
        $this->assertEquals('quiz', $quizAssessment->getSubtypeLabel());

        $surveyAssessment = $assessments->first(fn ($a) => $a->title === 'My Survey');
        $this->assertEquals('survey', $surveyAssessment->getSubtypeLabel());
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
}