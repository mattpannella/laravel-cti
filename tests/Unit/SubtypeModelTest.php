<?php

namespace Pannella\Cti\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Pannella\Cti\Tests\Fixtures\Assessment;
use Pannella\Cti\Tests\Fixtures\Quiz;
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
        Assessment::clearBootedModels();
        
        //create test tables
        $this->createTables();
        $this->seedTestData();
    }

    protected function tearDown(): void
    {
        DB::statement('DROP TABLE IF EXISTS assessment_quiz');
        DB::statement('DROP TABLE IF EXISTS assessment');
        DB::statement('DROP TABLE IF EXISTS assessment_type');
        
        // clear all event listeners
        Quiz::clearBootedModels();
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
    }

    protected function seedTestData(): void
    {
        DB::table('assessment_type')->insert([
            'id' => 1,
            'label' => 'quiz',
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
}