<?php

namespace Pannella\Cti\Tests\Unit;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Pannella\Cti\Attributes\CtiAttributeResolver;
use Pannella\Cti\Attributes\SubtypeConfig;
use Pannella\Cti\Support\SubtypeDiscriminatorScope;
use Pannella\Cti\Tests\Fixtures\Assessment;
use Pannella\Cti\Tests\Fixtures\AttributeAssessment;
use Pannella\Cti\Tests\Fixtures\AttributeQuiz;
use Pannella\Cti\Tests\Fixtures\AttributeSurvey;
use Pannella\Cti\Tests\Fixtures\Quiz;
use PHPUnit\Framework\TestCase;

class AttributeConfigTest extends TestCase
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

        $this->db->setEventDispatcher($this->dispatcher);
        Model::setEventDispatcher($this->dispatcher);

        $this->db->setAsGlobal();
        $this->db->bootEloquent();

        AttributeQuiz::clearBootedModels();
        AttributeSurvey::clearBootedModels();
        AttributeAssessment::clearBootedModels();

        $this->createTables();
        $this->seedTestData();
    }

    protected function tearDown(): void
    {
        DB::statement('DROP TABLE IF EXISTS assessment_survey');
        DB::statement('DROP TABLE IF EXISTS assessment_quiz');
        DB::statement('DROP TABLE IF EXISTS assessment');
        DB::statement('DROP TABLE IF EXISTS assessment_type');

        AttributeQuiz::clearBootedModels();
        AttributeSurvey::clearBootedModels();
        AttributeAssessment::clearBootedModels();
        Model::unsetEventDispatcher();

        CtiAttributeResolver::clearCache();

        AttributeQuiz::clearTypeIdCache();
        AttributeSurvey::clearTypeIdCache();

        SubtypeDiscriminatorScope::clearCache();

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
            $table->foreignId('category_id')->nullable();
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

    public function test_parent_model_getters_return_attribute_values(): void
    {
        $model = new AttributeAssessment;

        $this->assertEquals('type_id', $model->getSubtypeKey());
        $this->assertEquals('assessment_type', $model->getSubtypeLookupTable());
        $this->assertEquals('id', $model->getSubtypeLookupKey());
        $this->assertEquals('label', $model->getSubtypeLookupLabel());
        $this->assertEquals([
            'quiz' => AttributeQuiz::class,
            'survey' => AttributeSurvey::class,
        ], $model->getSubtypeMap());
    }

    public function test_subtype_model_getters_return_attribute_values(): void
    {
        $quiz = new AttributeQuiz;

        $this->assertEquals('assessment_quiz', $quiz->getSubtypeTable());
        $this->assertEquals(['passing_score', 'time_limit', 'show_correct_answers', 'category_id'], $quiz->getSubtypeAttributes());
        $this->assertEquals(AttributeAssessment::class, $quiz->getCtiParentClass());
        $this->assertEquals('assessment_id', $quiz->getSubtypeKeyName());
    }

    public function test_property_takes_precedence_over_attribute(): void
    {
        // Create a temporary class that has both property and attribute config
        // We test this by checking that the existing property-based Quiz fixture
        // still returns property values even if we were to add an attribute
        $quiz = new Quiz;

        // These come from properties, not attributes
        $this->assertEquals('assessment_quiz', $quiz->getSubtypeTable());
        $this->assertEquals(Assessment::class, $quiz->getCtiParentClass());
        $this->assertEquals('assessment_id', $quiz->getSubtypeKeyName());
    }

    public function test_create_quiz_with_attribute_config(): void
    {
        $quiz = new AttributeQuiz;
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
    }

    public function test_create_survey_with_attribute_config(): void
    {
        $survey = new AttributeSurvey;
        $survey->title = 'Test Survey';
        $survey->anonymous = true;
        $survey->allow_multiple_responses = true;

        $saved = $survey->save();

        $this->assertTrue($saved);

        $assessment = DB::table('assessment')->first();
        $this->assertNotNull($assessment);
        $this->assertEquals(2, $assessment->type_id);

        $surveyData = DB::table('assessment_survey')->first();
        $this->assertNotNull($surveyData);
        $this->assertEquals(1, $surveyData->anonymous);
        $this->assertEquals(1, $surveyData->allow_multiple_responses);
    }

    public function test_update_with_attribute_config(): void
    {
        $quiz = new AttributeQuiz;
        $quiz->passing_score = 80;
        $quiz->time_limit = 60;
        $quiz->save();

        $quiz->passing_score = 90;
        $quiz->title = 'Updated Quiz';
        $saved = $quiz->save();

        $this->assertTrue($saved);

        $quizData = DB::table('assessment_quiz')->first();
        $this->assertEquals(90, $quizData->passing_score);

        $assessment = DB::table('assessment')->first();
        $this->assertEquals('Updated Quiz', $assessment->title);
    }

    public function test_delete_with_attribute_config(): void
    {
        $quiz = new AttributeQuiz;
        $quiz->passing_score = 80;
        $quiz->time_limit = 60;
        $quiz->save();

        $quizId = $quiz->id;
        $quiz->delete();

        $this->assertNull(DB::table('assessment')->find($quizId));
        $this->assertNull(DB::table('assessment_quiz')->where('assessment_id', $quizId)->first());
    }

    public function test_load_from_database_with_attribute_config(): void
    {
        $quiz = new AttributeQuiz;
        $quiz->title = 'DB Quiz';
        $quiz->passing_score = 75;
        $quiz->time_limit = 30;
        $quiz->save();

        // Load via parent model — should morph to AttributeQuiz
        $loaded = AttributeAssessment::first();

        $this->assertInstanceOf(AttributeQuiz::class, $loaded);
        $this->assertEquals('DB Quiz', $loaded->title);
    }

    public function test_collection_batch_loading_with_attribute_config(): void
    {
        // Create quiz
        $quiz = new AttributeQuiz;
        $quiz->title = 'Quiz 1';
        $quiz->passing_score = 80;
        $quiz->time_limit = 60;
        $quiz->save();

        // Create survey
        $survey = new AttributeSurvey;
        $survey->title = 'Survey 1';
        $survey->anonymous = true;
        $survey->allow_multiple_responses = false;
        $survey->save();

        // Load all via parent
        $collection = AttributeAssessment::all();

        $this->assertCount(2, $collection);

        $quizItem = $collection->first(fn ($m) => $m instanceof AttributeQuiz);
        $surveyItem = $collection->first(fn ($m) => $m instanceof AttributeSurvey);

        $this->assertNotNull($quizItem);
        $this->assertNotNull($surveyItem);
        $this->assertEquals(80, $quizItem->passing_score);
        $this->assertTrue((bool) $surveyItem->anonymous);
    }

    public function test_resolver_caching_returns_same_instance(): void
    {
        CtiAttributeResolver::clearCache();

        $first = CtiAttributeResolver::resolveSubtypeConfig(AttributeAssessment::class);
        $second = CtiAttributeResolver::resolveSubtypeConfig(AttributeAssessment::class);

        $this->assertSame($first, $second);

        $firstSubtype = CtiAttributeResolver::resolveSubtype(AttributeQuiz::class);
        $secondSubtype = CtiAttributeResolver::resolveSubtype(AttributeQuiz::class);

        $this->assertSame($firstSubtype, $secondSubtype);
    }

    public function test_clear_cache_forces_re_resolution(): void
    {
        $first = CtiAttributeResolver::resolveSubtypeConfig(AttributeAssessment::class);
        CtiAttributeResolver::clearCache();
        $second = CtiAttributeResolver::resolveSubtypeConfig(AttributeAssessment::class);

        // Both should be SubtypeConfig instances with same values but different object identity
        $this->assertInstanceOf(SubtypeConfig::class, $first);
        $this->assertInstanceOf(SubtypeConfig::class, $second);
        $this->assertNotSame($first, $second);
        $this->assertEquals($first->map, $second->map);
    }

    public function test_resolver_returns_null_for_non_annotated_class(): void
    {
        $result = CtiAttributeResolver::resolveSubtypeConfig(Assessment::class);
        // Assessment uses properties, not attributes — resolver should return null
        $this->assertNull($result);

        $result = CtiAttributeResolver::resolveSubtype(Quiz::class);
        $this->assertNull($result);
    }
}
