<?php

namespace Pannella\Cti\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Pannella\Cti\Tests\Fixtures\Assessment;
use Pannella\Cti\Tests\Fixtures\DirectAssessment;
use Pannella\Cti\Tests\Fixtures\DirectQuiz;
use Pannella\Cti\Tests\Fixtures\DirectSurvey;
use Pannella\Cti\Tests\Fixtures\DirectSoftDeletableQuiz;
use Pannella\Cti\Tests\Fixtures\DirectOverlappingColumnsQuiz;
use Pannella\Cti\Tests\Fixtures\DirectMinimalQuiz;
use Pannella\Cti\Tests\Fixtures\DirectNoInheritQuiz;
use Pannella\Cti\Tests\Fixtures\DirectExcludeFieldQuiz;
use Pannella\Cti\Tests\Fixtures\DirectQuizQuestion;
use Pannella\Cti\Tests\Fixtures\DirectQuizSettings;
use Pannella\Cti\Tests\Fixtures\DirectAssessmentTag;
use Pannella\Cti\Tests\Fixtures\AttributeDirectAssessment;
use Pannella\Cti\Tests\Fixtures\AttributeDirectQuiz;
use Pannella\Cti\Tests\Fixtures\AttributeDirectSurvey;
use Pannella\Cti\Exceptions\SubtypeException;
use Pannella\Cti\Support\SubtypeDiscriminatorScope;
use Pannella\Cti\SubtypeModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Config\Repository as ConfigRepository;

class DirectDiscriminatorTest extends TestCase
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

        DirectQuiz::clearBootedModels();
        DirectSurvey::clearBootedModels();
        DirectAssessment::clearBootedModels();
        DirectSoftDeletableQuiz::clearBootedModels();
        DirectOverlappingColumnsQuiz::clearBootedModels();
        DirectMinimalQuiz::clearBootedModels();
        DirectNoInheritQuiz::clearBootedModels();
        DirectExcludeFieldQuiz::clearBootedModels();
        AttributeDirectQuiz::clearBootedModels();
        AttributeDirectSurvey::clearBootedModels();
        AttributeDirectAssessment::clearBootedModels();

        $this->createTables();
    }

    protected function tearDown(): void
    {
        DB::statement('DROP TABLE IF EXISTS direct_quiz_settings');
        DB::statement('DROP TABLE IF EXISTS direct_quiz_questions');
        DB::statement('DROP TABLE IF EXISTS direct_assessment_tag');
        DB::statement('DROP TABLE IF EXISTS direct_assessment_survey');
        DB::statement('DROP TABLE IF EXISTS direct_assessment_quiz');
        DB::statement('DROP TABLE IF EXISTS direct_assessment');

        DirectQuiz::clearBootedModels();
        DirectSurvey::clearBootedModels();
        DirectAssessment::clearBootedModels();
        DirectSoftDeletableQuiz::clearBootedModels();
        DirectOverlappingColumnsQuiz::clearBootedModels();
        DirectMinimalQuiz::clearBootedModels();
        DirectNoInheritQuiz::clearBootedModels();
        DirectExcludeFieldQuiz::clearBootedModels();
        AttributeDirectQuiz::clearBootedModels();
        AttributeDirectSurvey::clearBootedModels();
        AttributeDirectAssessment::clearBootedModels();
        Model::unsetEventDispatcher();

        SubtypeDiscriminatorScope::clearCache();

        DirectQuiz::clearTypeIdCache();
        DirectSurvey::clearTypeIdCache();
        DirectSoftDeletableQuiz::clearTypeIdCache();
        DirectMinimalQuiz::clearTypeIdCache();
        DirectNoInheritQuiz::clearTypeIdCache();
        DirectExcludeFieldQuiz::clearTypeIdCache();
        AttributeDirectQuiz::clearTypeIdCache();
        AttributeDirectSurvey::clearTypeIdCache();

        $this->resetValidationCache();

        $this->db = null;
        $this->dispatcher = null;

        parent::tearDown();
    }

    protected function createTables(): void
    {
        DB::schema()->create('direct_assessment', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::schema()->create('direct_assessment_quiz', function (Blueprint $table) {
            $table->foreignId('assessment_id')->constrained('direct_assessment')->onDelete('cascade');
            $table->integer('passing_score');
            $table->integer('time_limit')->nullable();
            $table->primary('assessment_id');
        });

        DB::schema()->create('direct_assessment_survey', function (Blueprint $table) {
            $table->foreignId('assessment_id')->constrained('direct_assessment')->onDelete('cascade');
            $table->boolean('anonymous')->default(false);
            $table->boolean('allow_multiple_responses')->default(false);
            $table->primary('assessment_id');
        });

        DB::schema()->create('direct_quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id');
            $table->string('question_text');
            $table->integer('points')->default(1);
        });

        DB::schema()->create('direct_quiz_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id');
            $table->boolean('randomize_questions')->default(false);
            $table->boolean('show_progress_bar')->default(true);
        });

        DB::schema()->create('direct_assessment_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id');
            $table->string('tag_name');
        });
    }

    protected function seedQuizAndSurvey(): void
    {
        $now = date('Y-m-d H:i:s');

        DB::table('direct_assessment')->insert([
            ['id' => 1, 'type' => 'quiz', 'title' => 'Math Quiz', 'description' => 'A math quiz', 'enabled' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'type' => 'survey', 'title' => 'Feedback Survey', 'description' => 'A survey', 'enabled' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('direct_assessment_quiz')->insert([
            ['assessment_id' => 1, 'passing_score' => 80, 'time_limit' => 60],
        ]);

        DB::table('direct_assessment_survey')->insert([
            ['assessment_id' => 2, 'anonymous' => true, 'allow_multiple_responses' => false],
        ]);
    }

    protected function createQuizRecord(array $assessmentData = [], array $quizData = []): void
    {
        $defaultAssessment = [
            'id' => 1,
            'type' => 'quiz',
            'title' => 'Test Quiz',
            'description' => 'Test Description',
            'enabled' => true,
        ];

        $defaultQuiz = [
            'assessment_id' => 1,
            'passing_score' => 70,
            'time_limit' => 30,
        ];

        DB::table('direct_assessment')->insert(array_merge($defaultAssessment, $assessmentData));
        DB::table('direct_assessment_quiz')->insert(array_merge($defaultQuiz, $quizData));
    }

    protected function createSurveyRecord(array $assessmentData = [], array $surveyData = []): void
    {
        $defaultAssessment = [
            'id' => 1,
            'type' => 'survey',
            'title' => 'Test Survey',
            'description' => 'Test Description',
            'enabled' => true,
        ];

        $defaultSurvey = [
            'assessment_id' => 1,
            'anonymous' => false,
            'allow_multiple_responses' => false,
        ];

        DB::table('direct_assessment')->insert(array_merge($defaultAssessment, $assessmentData));
        DB::table('direct_assessment_survey')->insert(array_merge($defaultSurvey, $surveyData));
    }

    private function resetValidationCache(): void
    {
        $ref = new \ReflectionProperty(SubtypeModel::class, 'validatedSubtypeColumns');
        $ref->setValue(null, []);
    }

    private function setCtiConfig(string $strategy): void
    {
        $config = new ConfigRepository(['cti' => ['on_missing_subtype_data' => $strategy]]);
        Container::getInstance()->instance('config', $config);
    }

    // ============================================================
    // usesLookupTable()
    // ============================================================

    public function testUsesLookupTableReturnsFalseForDirectMode(): void
    {
        $model = new DirectAssessment();
        $this->assertFalse($model->usesLookupTable());
    }

    public function testUsesLookupTableReturnsFalseForAttributeDirectMode(): void
    {
        $model = new AttributeDirectAssessment();
        $this->assertFalse($model->usesLookupTable());
    }

    public function testUsesLookupTableReturnsTrueForLookupMode(): void
    {
        $model = new Assessment();
        $this->assertTrue($model->usesLookupTable());
    }

    // ============================================================
    // Getter methods
    // ============================================================

    public function testGetSubtypeLookupTableReturnsNullForDirectMode(): void
    {
        $model = new DirectAssessment();
        $this->assertNull($model->getSubtypeLookupTable());
    }

    public function testGetSubtypeLookupKeyReturnsNullForDirectMode(): void
    {
        $model = new DirectAssessment();
        $this->assertNull($model->getSubtypeLookupKey());
    }

    public function testGetSubtypeLookupLabelReturnsNullForDirectMode(): void
    {
        $model = new DirectAssessment();
        $this->assertNull($model->getSubtypeLookupLabel());
    }

    public function testGetSubtypeKeyReturnsColumnName(): void
    {
        $model = new DirectAssessment();
        $this->assertEquals('type', $model->getSubtypeKey());
    }

    public function testGetSubtypeMap(): void
    {
        $model = new DirectAssessment();
        $map = $model->getSubtypeMap();

        $this->assertIsArray($map);
        $this->assertArrayHasKey('quiz', $map);
        $this->assertArrayHasKey('survey', $map);
        $this->assertEquals(DirectQuiz::class, $map['quiz']);
        $this->assertEquals(DirectSurvey::class, $map['survey']);
    }

    // ============================================================
    // resolveSubtypeLabel
    // ============================================================

    public function testResolveSubtypeLabelDirectMode(): void
    {
        $label = DirectAssessment::resolveSubtypeLabel('quiz');
        $this->assertEquals('quiz', $label);
    }

    public function testResolveSubtypeLabelDirectModeReturnsStringAsIs(): void
    {
        $label = DirectAssessment::resolveSubtypeLabel('survey');
        $this->assertEquals('survey', $label);
    }

    public function testResolveSubtypeLabelReturnsNullForEmptyInput(): void
    {
        $label = DirectAssessment::resolveSubtypeLabel('');
        $this->assertNull($label);
    }

    // ============================================================
    // Creating models
    // ============================================================

    public function testCreateSubtypeSetsDiscriminatorToLabel(): void
    {
        $quiz = new DirectQuiz();
        $quiz->title = 'Test Quiz';
        $quiz->passing_score = 80;
        $quiz->save();

        $this->assertEquals('quiz', $quiz->type);

        $record = DB::table('direct_assessment')->where('id', $quiz->id)->first();
        $this->assertEquals('quiz', $record->type);
    }

    public function testCreateSurveyDirectMode(): void
    {
        $survey = new DirectSurvey();
        $survey->title = 'Test Survey';
        $survey->anonymous = true;
        $survey->save();

        $this->assertEquals('survey', $survey->type);

        $record = DB::table('direct_assessment')->where('id', $survey->id)->first();
        $this->assertEquals('survey', $record->type);
    }

    public function testCreateWithOnlySubtypeAttributes(): void
    {
        $quiz = new DirectQuiz();
        $quiz->passing_score = 80;
        $quiz->time_limit = 60;

        $saved = $quiz->save();

        $this->assertTrue($saved);

        $assessment = DB::table('direct_assessment')->first();
        $this->assertNotNull($assessment);
        $this->assertEquals('quiz', $assessment->type);

        $quizData = DB::table('direct_assessment_quiz')->first();
        $this->assertNotNull($quizData);
        $this->assertEquals(80, $quizData->passing_score);
        $this->assertEquals($assessment->id, $quizData->assessment_id);
    }

    public function testPresetDiscriminatorNotOverridden(): void
    {
        $quiz = new DirectQuiz();
        $quiz->type = 'quiz';
        $quiz->title = 'Manually Typed Quiz';
        $quiz->passing_score = 70;

        $quiz->save();

        $assessment = DB::table('direct_assessment')->where('id', $quiz->id)->first();
        $this->assertEquals('quiz', $assessment->type);
    }

    public function testMassAssignmentCreateDirectMode(): void
    {
        $quiz = DirectQuiz::create([
            'title' => 'Created Quiz',
            'passing_score' => 85,
            'time_limit' => 30,
        ]);

        $this->assertEquals('quiz', $quiz->type);
        $this->assertEquals('Created Quiz', $quiz->title);
        $this->assertEquals(85, $quiz->passing_score);
        $this->assertEquals(30, $quiz->time_limit);
    }

    public function testCreatingTypeIdCacheWorksForDirectMode(): void
    {
        // First create should resolve and cache
        $quiz1 = new DirectQuiz();
        $quiz1->title = 'Quiz 1';
        $quiz1->passing_score = 70;
        $quiz1->save();

        // Second create should use cache
        $quiz2 = new DirectQuiz();
        $quiz2->title = 'Quiz 2';
        $quiz2->passing_score = 80;
        $quiz2->save();

        $this->assertEquals('quiz', $quiz1->type);
        $this->assertEquals('quiz', $quiz2->type);
    }

    // ============================================================
    // Loading models
    // ============================================================

    public function testParentModelMorphsToCorrectSubtype(): void
    {
        $this->seedQuizAndSurvey();

        $assessments = DirectAssessment::all();

        $this->assertCount(2, $assessments);
        $this->assertInstanceOf(DirectQuiz::class, $assessments[0]);
        $this->assertInstanceOf(DirectSurvey::class, $assessments[1]);
    }

    public function testSubtypeDataLoadsCorrectly(): void
    {
        $this->seedQuizAndSurvey();

        $assessments = DirectAssessment::all();
        $quiz = $assessments[0];

        $this->assertEquals(80, $quiz->passing_score);
        $this->assertEquals(60, $quiz->time_limit);
    }

    public function testSurveySubtypeDataLoadsCorrectly(): void
    {
        $this->seedQuizAndSurvey();

        $assessments = DirectAssessment::all();
        $survey = $assessments[1];

        $this->assertEquals(true, $survey->anonymous);
        $this->assertEquals(false, $survey->allow_multiple_responses);
    }

    public function testParentModelFindMorphsToSubtype(): void
    {
        $this->createQuizRecord(
            ['id' => 1, 'title' => 'Test Quiz'],
            ['assessment_id' => 1, 'passing_score' => 80]
        );

        $assessment = DirectAssessment::find(1);

        $this->assertInstanceOf(DirectQuiz::class, $assessment);
        $this->assertEquals('Test Quiz', $assessment->title);
        $this->assertEquals(80, $assessment->passing_score);
    }

    public function testInvalidTypeReturnsBaseAssessment(): void
    {
        $now = date('Y-m-d H:i:s');
        DB::table('direct_assessment')->insert([
            'id' => 1,
            'type' => 'unknown_type',
            'title' => 'Invalid Assessment',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $assessment = DirectAssessment::find(1);

        $this->assertInstanceOf(DirectAssessment::class, $assessment);
        $this->assertNotInstanceOf(DirectQuiz::class, $assessment);
        $this->assertNotInstanceOf(DirectSurvey::class, $assessment);
        $this->assertEquals('Invalid Assessment', $assessment->title);
    }

    public function testNullValues(): void
    {
        $quiz = new DirectQuiz();
        $quiz->title = 'Test Quiz';
        $quiz->description = null;
        $quiz->time_limit = null;
        $quiz->passing_score = 70;

        $this->assertTrue($quiz->save());

        $loaded = DirectQuiz::find($quiz->id);
        $this->assertNull($loaded->description);
        $this->assertNull($loaded->time_limit);
    }

    // ============================================================
    // Query scoping
    // ============================================================

    public function testSubtypeQueryScopeFiltersCorrectly(): void
    {
        $this->seedQuizAndSurvey();

        $quizzes = DirectQuiz::all();
        $this->assertCount(1, $quizzes);
        $this->assertInstanceOf(DirectQuiz::class, $quizzes->first());
        $this->assertEquals('Math Quiz', $quizzes->first()->title);
    }

    public function testSurveyQueryScopeFiltersCorrectly(): void
    {
        $this->seedQuizAndSurvey();

        $surveys = DirectSurvey::all();
        $this->assertCount(1, $surveys);
        $this->assertInstanceOf(DirectSurvey::class, $surveys->first());
        $this->assertEquals('Feedback Survey', $surveys->first()->title);
    }

    public function testWithoutGlobalScopeReturnsAllRecords(): void
    {
        $this->seedQuizAndSurvey();

        $all = DirectQuiz::withoutGlobalScope(SubtypeDiscriminatorScope::class)->get();
        $this->assertCount(2, $all);
    }

    public function testDiscriminatorFilteringWorksWithOtherClauses(): void
    {
        $this->createQuizRecord(['id' => 1, 'title' => 'Alpha Quiz'], ['assessment_id' => 1, 'passing_score' => 80]);
        $this->createQuizRecord(['id' => 2, 'title' => 'Beta Quiz'], ['assessment_id' => 2, 'passing_score' => 90]);
        $this->createSurveyRecord(['id' => 3, 'title' => 'Alpha Survey'], ['assessment_id' => 3]);

        $result = DirectQuiz::where('title', 'like', 'Alpha%')->get();
        $this->assertCount(1, $result);
        $this->assertEquals('Alpha Quiz', $result->first()->title);
    }

    public function testDiscriminatorFilteringWorksWithCount(): void
    {
        $this->createQuizRecord(['id' => 1], ['assessment_id' => 1, 'passing_score' => 80]);
        $this->createQuizRecord(['id' => 2], ['assessment_id' => 2, 'passing_score' => 90]);
        $this->createSurveyRecord(['id' => 3], ['assessment_id' => 3]);

        $this->assertEquals(2, DirectQuiz::count());
        $this->assertEquals(1, DirectSurvey::count());
    }

    public function testFindRespectsDiscriminatorFiltering(): void
    {
        $this->createSurveyRecord(['id' => 1], ['assessment_id' => 1]);

        // Trying to find a survey via Quiz model should return null
        $quiz = DirectQuiz::find(1);
        $this->assertNull($quiz);
    }

    // ============================================================
    // Find and Update
    // ============================================================

    public function testFindLoadsSubtypeData(): void
    {
        $this->seedQuizAndSurvey();

        $quiz = DirectQuiz::find(1);
        $this->assertInstanceOf(DirectQuiz::class, $quiz);
        $this->assertEquals('Math Quiz', $quiz->title);
        $this->assertEquals(80, $quiz->passing_score);
    }

    public function testUpdateSubtypeAttributes(): void
    {
        $this->seedQuizAndSurvey();

        $quiz = DirectQuiz::find(1);
        $quiz->passing_score = 90;
        $quiz->save();

        $record = DB::table('direct_assessment_quiz')->where('assessment_id', 1)->first();
        $this->assertEquals(90, $record->passing_score);
    }

    public function testSaveWithOnlySubtypeAttributesModified(): void
    {
        $this->createQuizRecord(
            ['id' => 1, 'title' => 'Original Title'],
            ['assessment_id' => 1, 'passing_score' => 70, 'time_limit' => 30]
        );

        $quiz = DirectQuiz::find(1);
        $quiz->passing_score = 85;
        $result = $quiz->save();

        $this->assertTrue($result);

        $assessmentRecord = DB::table('direct_assessment')->where('id', 1)->first();
        $this->assertEquals('Original Title', $assessmentRecord->title);

        $quizRecord = DB::table('direct_assessment_quiz')->where('assessment_id', 1)->first();
        $this->assertEquals(85, $quizRecord->passing_score);
    }

    public function testSaveWithOnlyParentAttributesModified(): void
    {
        $this->createQuizRecord(
            ['id' => 1, 'title' => 'Original Title'],
            ['assessment_id' => 1, 'passing_score' => 70, 'time_limit' => 30]
        );

        $quiz = DirectQuiz::find(1);
        $quiz->title = 'Updated Title';
        $result = $quiz->save();

        $this->assertTrue($result);

        $assessmentRecord = DB::table('direct_assessment')->where('id', 1)->first();
        $this->assertEquals('Updated Title', $assessmentRecord->title);

        $quizRecord = DB::table('direct_assessment_quiz')->where('assessment_id', 1)->first();
        $this->assertEquals(70, $quizRecord->passing_score);
    }

    public function testSaveWithNoChanges(): void
    {
        $this->createQuizRecord(
            ['id' => 1, 'title' => 'Test Quiz'],
            ['assessment_id' => 1, 'passing_score' => 80]
        );

        $quiz = DirectQuiz::find(1);

        DB::connection()->enableQueryLog();
        $result = $quiz->save();
        $queryLog = DB::connection()->getQueryLog();

        $this->assertTrue($result);

        $subtypeUpdates = array_filter($queryLog, fn ($q) =>
            strpos($q['query'], 'update') !== false
            && strpos($q['query'], 'direct_assessment_quiz') !== false
        );
        $this->assertCount(0, $subtypeUpdates);
    }

    // ============================================================
    // Delete
    // ============================================================

    public function testDeleteRemovesBothRecords(): void
    {
        $this->seedQuizAndSurvey();

        $quiz = DirectQuiz::find(1);
        $quiz->delete();

        $this->assertNull(DB::table('direct_assessment')->where('id', 1)->first());
        $this->assertNull(DB::table('direct_assessment_quiz')->where('assessment_id', 1)->first());
    }

    public function testDeleteModelWithNoSubtypeRecord(): void
    {
        $now = date('Y-m-d H:i:s');
        DB::table('direct_assessment')->insert([
            'id' => 1,
            'type' => 'quiz',
            'title' => 'Orphan Quiz',
            'enabled' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $quiz = DirectQuiz::find(1);
        $this->assertNotNull($quiz);

        $result = $quiz->delete();
        $this->assertTrue($result);

        $this->assertNull(DB::table('direct_assessment')->where('id', 1)->first());
    }

    // ============================================================
    // Soft Deletes
    // ============================================================

    public function testSoftDeletePreservesSubtypeRow(): void
    {
        $this->createQuizRecord(
            ['id' => 1, 'title' => 'Soft Delete Quiz'],
            ['assessment_id' => 1, 'passing_score' => 80]
        );

        // Use withoutGlobalScopes since DirectSoftDeletableQuiz isn't in the subtype map
        $quiz = DirectSoftDeletableQuiz::withoutGlobalScopes()->find(1);
        $this->assertNotNull($quiz);

        $quiz->delete();

        // Parent should be soft-deleted
        $assessment = DB::table('direct_assessment')->where('id', 1)->first();
        $this->assertNotNull($assessment);
        $this->assertNotNull($assessment->deleted_at);

        // Subtype row should still exist
        $this->assertNotNull(DB::table('direct_assessment_quiz')->where('assessment_id', 1)->first());
    }

    public function testForceDeleteRemovesSubtypeRow(): void
    {
        $this->createQuizRecord(
            ['id' => 1, 'title' => 'Force Delete Quiz'],
            ['assessment_id' => 1, 'passing_score' => 80]
        );

        $quiz = DirectSoftDeletableQuiz::withoutGlobalScopes()->find(1);
        $this->assertNotNull($quiz);

        $quiz->forceDelete();

        $this->assertNull(DB::table('direct_assessment')->where('id', 1)->first());
        $this->assertNull(DB::table('direct_assessment_quiz')->where('assessment_id', 1)->first());
    }

    public function testRestoreAfterSoftDelete(): void
    {
        $this->createQuizRecord(
            ['id' => 1, 'title' => 'Restore Quiz'],
            ['assessment_id' => 1, 'passing_score' => 80]
        );

        $quiz = DirectSoftDeletableQuiz::withoutGlobalScopes()->find(1);
        $this->assertNotNull($quiz);

        $quiz->delete();

        // Should be soft-deleted (deleted_at set)
        $record = DB::table('direct_assessment')->where('id', 1)->first();
        $this->assertNotNull($record->deleted_at);

        // Restore
        $quiz->restore();

        $restored = DirectSoftDeletableQuiz::withoutGlobalScopes()->find(1);
        $this->assertNotNull($restored);
        $this->assertEquals('Restore Quiz', $restored->title);
    }

    // ============================================================
    // Query Builder — WHERE variants
    // ============================================================

    public function testQueryBuilderWithSubtypeConditions(): void
    {
        $this->createQuizRecord(['id' => 1], ['assessment_id' => 1, 'passing_score' => 70]);
        $this->createQuizRecord(['id' => 2], ['assessment_id' => 2, 'passing_score' => 80]);
        $this->createQuizRecord(['id' => 3], ['assessment_id' => 3, 'passing_score' => 90]);

        $highScoreQuizzes = DirectQuiz::where('passing_score', '>', 75)->get();

        $this->assertCount(2, $highScoreQuizzes);
        $this->assertEquals([80, 90], $highScoreQuizzes->pluck('passing_score')->all());
    }

    public function testWhereInWithSubtypeColumn(): void
    {
        $this->createQuizRecord(['id' => 1], ['assessment_id' => 1, 'passing_score' => 70]);
        $this->createQuizRecord(['id' => 2], ['assessment_id' => 2, 'passing_score' => 80]);
        $this->createQuizRecord(['id' => 3], ['assessment_id' => 3, 'passing_score' => 90]);

        $result = DirectQuiz::whereIn('passing_score', [70, 90])->get();

        $this->assertCount(2, $result);
        $scores = $result->pluck('passing_score')->sort()->values()->all();
        $this->assertEquals([70, 90], $scores);
    }

    public function testWhereNotInWithSubtypeColumn(): void
    {
        $this->createQuizRecord(['id' => 1], ['assessment_id' => 1, 'passing_score' => 70]);
        $this->createQuizRecord(['id' => 2], ['assessment_id' => 2, 'passing_score' => 80]);
        $this->createQuizRecord(['id' => 3], ['assessment_id' => 3, 'passing_score' => 90]);

        $result = DirectQuiz::whereNotIn('passing_score', [70, 90])->get();

        $this->assertCount(1, $result);
        $this->assertEquals(80, $result->first()->passing_score);
    }

    public function testWhereBetweenWithSubtypeColumn(): void
    {
        $this->createQuizRecord(['id' => 1], ['assessment_id' => 1, 'passing_score' => 60]);
        $this->createQuizRecord(['id' => 2], ['assessment_id' => 2, 'passing_score' => 75]);
        $this->createQuizRecord(['id' => 3], ['assessment_id' => 3, 'passing_score' => 95]);

        $result = DirectQuiz::whereBetween('passing_score', [70, 90])->get();

        $this->assertCount(1, $result);
        $this->assertEquals(75, $result->first()->passing_score);
    }

    public function testWhereNullWithSubtypeColumn(): void
    {
        $this->createQuizRecord(['id' => 1], ['assessment_id' => 1, 'passing_score' => 80, 'time_limit' => 60]);
        $this->createQuizRecord(['id' => 2], ['assessment_id' => 2, 'passing_score' => 90, 'time_limit' => null]);

        $result = DirectQuiz::whereNull('time_limit')->get();

        $this->assertCount(1, $result);
        $this->assertEquals(2, $result->first()->id);
    }

    public function testWhereNotNullWithSubtypeColumn(): void
    {
        $this->createQuizRecord(['id' => 1], ['assessment_id' => 1, 'passing_score' => 80, 'time_limit' => 60]);
        $this->createQuizRecord(['id' => 2], ['assessment_id' => 2, 'passing_score' => 90, 'time_limit' => null]);

        $result = DirectQuiz::whereNotNull('time_limit')->get();

        $this->assertCount(1, $result);
        $this->assertEquals(1, $result->first()->id);
    }

    public function testWhereColumnWithSubtypeColumn(): void
    {
        $this->createQuizRecord(['id' => 1], ['assessment_id' => 1, 'passing_score' => 80, 'time_limit' => 80]);
        $this->createQuizRecord(['id' => 2], ['assessment_id' => 2, 'passing_score' => 90, 'time_limit' => 60]);

        $result = DirectQuiz::whereColumn('passing_score', '=', 'time_limit')->get();

        $this->assertCount(1, $result);
        $this->assertEquals(1, $result->first()->id);
    }

    public function testChainedQueryBuilderAddsJoinOnce(): void
    {
        $this->createQuizRecord(['id' => 1], ['assessment_id' => 1, 'passing_score' => 80, 'time_limit' => 60]);
        $this->createQuizRecord(['id' => 2], ['assessment_id' => 2, 'passing_score' => 90, 'time_limit' => 30]);
        $this->createQuizRecord(['id' => 3], ['assessment_id' => 3, 'passing_score' => 70, 'time_limit' => 45]);

        $result = DirectQuiz::where('passing_score', '>', 70)
            ->orderBy('time_limit')
            ->get();

        $this->assertCount(2, $result);
        $this->assertEquals(30, $result[0]->time_limit);
        $this->assertEquals(60, $result[1]->time_limit);
    }

    // ============================================================
    // Query Builder — ORDER BY, GROUP BY, HAVING
    // ============================================================

    public function testOrderByWithSubtypeColumn(): void
    {
        $this->createQuizRecord(['id' => 1], ['assessment_id' => 1, 'passing_score' => 90]);
        $this->createQuizRecord(['id' => 2], ['assessment_id' => 2, 'passing_score' => 70]);
        $this->createQuizRecord(['id' => 3], ['assessment_id' => 3, 'passing_score' => 80]);

        $quizzes = DirectQuiz::orderBy('passing_score', 'asc')->get();

        $this->assertCount(3, $quizzes);
        $this->assertEquals([70, 80, 90], $quizzes->pluck('passing_score')->all());
    }

    public function testOrderByDescWithSubtypeColumn(): void
    {
        $this->createQuizRecord(['id' => 1], ['assessment_id' => 1, 'passing_score' => 90]);
        $this->createQuizRecord(['id' => 2], ['assessment_id' => 2, 'passing_score' => 70]);
        $this->createQuizRecord(['id' => 3], ['assessment_id' => 3, 'passing_score' => 80]);

        $quizzes = DirectQuiz::orderBy('passing_score', 'desc')->get();

        $this->assertCount(3, $quizzes);
        $this->assertEquals([90, 80, 70], $quizzes->pluck('passing_score')->all());
    }

    public function testGroupByWithSubtypeColumn(): void
    {
        $this->createQuizRecord(['id' => 1], ['assessment_id' => 1, 'passing_score' => 80]);
        $this->createQuizRecord(['id' => 2], ['assessment_id' => 2, 'passing_score' => 80]);
        $this->createQuizRecord(['id' => 3], ['assessment_id' => 3, 'passing_score' => 90]);

        $grouped = DirectQuiz::selectRaw('passing_score, COUNT(*) as count')
            ->groupBy('passing_score')
            ->orderBy('passing_score')
            ->get();

        $this->assertCount(2, $grouped);
        $this->assertEquals(80, $grouped[0]->passing_score);
        $this->assertEquals(2, $grouped[0]->count);
        $this->assertEquals(90, $grouped[1]->passing_score);
        $this->assertEquals(1, $grouped[1]->count);
    }

    public function testHavingWithSubtypeColumn(): void
    {
        $this->createQuizRecord(['id' => 1], ['assessment_id' => 1, 'passing_score' => 80]);
        $this->createQuizRecord(['id' => 2], ['assessment_id' => 2, 'passing_score' => 80]);
        $this->createQuizRecord(['id' => 3], ['assessment_id' => 3, 'passing_score' => 90]);

        $result = DirectQuiz::selectRaw('passing_score, COUNT(*) as count')
            ->groupBy('passing_score')
            ->having('passing_score', '>', 85)
            ->get();

        $this->assertCount(1, $result);
        $this->assertEquals(90, $result->first()->passing_score);
    }

    // ============================================================
    // Query Builder — Aggregates
    // ============================================================

    public function testAggregateCountWithSubtypeColumn(): void
    {
        $this->createQuizRecord(['id' => 1], ['assessment_id' => 1, 'passing_score' => 80]);
        $this->createQuizRecord(['id' => 2], ['assessment_id' => 2, 'passing_score' => 90]);
        $this->createQuizRecord(['id' => 3], ['assessment_id' => 3, 'passing_score' => 70]);

        $count = DirectQuiz::where('passing_score', '>=', 80)->count();

        $this->assertEquals(2, $count);
    }

    public function testAggregateSumWithSubtypeColumn(): void
    {
        $this->createQuizRecord(['id' => 1], ['assessment_id' => 1, 'passing_score' => 80]);
        $this->createQuizRecord(['id' => 2], ['assessment_id' => 2, 'passing_score' => 90]);
        $this->createQuizRecord(['id' => 3], ['assessment_id' => 3, 'passing_score' => 70]);

        $sum = DirectQuiz::where('passing_score', '>=', 0)->sum('passing_score');

        $this->assertEquals(240, $sum);
    }

    public function testAggregateAvgWithSubtypeColumn(): void
    {
        $this->createQuizRecord(['id' => 1], ['assessment_id' => 1, 'passing_score' => 80]);
        $this->createQuizRecord(['id' => 2], ['assessment_id' => 2, 'passing_score' => 90]);
        $this->createQuizRecord(['id' => 3], ['assessment_id' => 3, 'passing_score' => 70]);

        $avg = DirectQuiz::where('passing_score', '>=', 0)->avg('passing_score');

        $this->assertEquals(80, $avg);
    }

    public function testAggregateMaxWithSubtypeColumn(): void
    {
        $this->createQuizRecord(['id' => 1], ['assessment_id' => 1, 'passing_score' => 80]);
        $this->createQuizRecord(['id' => 2], ['assessment_id' => 2, 'passing_score' => 90]);
        $this->createQuizRecord(['id' => 3], ['assessment_id' => 3, 'passing_score' => 70]);

        $max = DirectQuiz::where('passing_score', '>=', 0)->max('passing_score');

        $this->assertEquals(90, $max);
    }

    public function testAggregateMinWithSubtypeColumn(): void
    {
        $this->createQuizRecord(['id' => 1], ['assessment_id' => 1, 'passing_score' => 80]);
        $this->createQuizRecord(['id' => 2], ['assessment_id' => 2, 'passing_score' => 90]);
        $this->createQuizRecord(['id' => 3], ['assessment_id' => 3, 'passing_score' => 70]);

        $min = DirectQuiz::where('passing_score', '>=', 0)->min('passing_score');

        $this->assertEquals(70, $min);
    }

    // ============================================================
    // Query Builder — Select, Pluck, Value
    // ============================================================

    public function testSelectWithSubtypeColumns(): void
    {
        $this->createQuizRecord(
            ['id' => 1, 'title' => 'Test Quiz'],
            ['assessment_id' => 1, 'passing_score' => 80, 'time_limit' => 60]
        );

        $quiz = DirectQuiz::select('passing_score')->first();

        $this->assertEquals(80, $quiz->passing_score);
    }

    public function testPluckOnSubtypeColumn(): void
    {
        $this->createQuizRecord(['id' => 1], ['assessment_id' => 1, 'passing_score' => 70]);
        $this->createQuizRecord(['id' => 2], ['assessment_id' => 2, 'passing_score' => 80]);
        $this->createQuizRecord(['id' => 3], ['assessment_id' => 3, 'passing_score' => 90]);

        $scores = DirectQuiz::all()->pluck('passing_score')->all();

        $this->assertEquals([70, 80, 90], $scores);
    }

    // ============================================================
    // Query Builder — Mass Update, Increment, Decrement
    // ============================================================

    public function testMassUpdateSubtypeColumns(): void
    {
        $this->createQuizRecord(['id' => 1], ['assessment_id' => 1, 'passing_score' => 70]);
        $this->createQuizRecord(['id' => 2], ['assessment_id' => 2, 'passing_score' => 80]);

        DirectQuiz::where('passing_score', '<', 80)->update(['passing_score' => 75]);

        $record = DB::table('direct_assessment_quiz')->where('assessment_id', 1)->first();
        $this->assertEquals(75, $record->passing_score);

        // Unaffected record
        $record2 = DB::table('direct_assessment_quiz')->where('assessment_id', 2)->first();
        $this->assertEquals(80, $record2->passing_score);
    }

    public function testIncrementSubtypeColumn(): void
    {
        $this->createQuizRecord(['id' => 1], ['assessment_id' => 1, 'passing_score' => 70]);
        $this->createQuizRecord(['id' => 2], ['assessment_id' => 2, 'passing_score' => 80]);

        DirectQuiz::where('passing_score', '>=', 0)->increment('passing_score', 5);

        $this->assertEquals(75, DB::table('direct_assessment_quiz')->where('assessment_id', 1)->first()->passing_score);
        $this->assertEquals(85, DB::table('direct_assessment_quiz')->where('assessment_id', 2)->first()->passing_score);
    }

    public function testDecrementSubtypeColumn(): void
    {
        $this->createQuizRecord(['id' => 1], ['assessment_id' => 1, 'passing_score' => 70]);
        $this->createQuizRecord(['id' => 2], ['assessment_id' => 2, 'passing_score' => 80]);

        DirectQuiz::where('passing_score', '>=', 0)->decrement('passing_score', 5);

        $this->assertEquals(65, DB::table('direct_assessment_quiz')->where('assessment_id', 1)->first()->passing_score);
        $this->assertEquals(75, DB::table('direct_assessment_quiz')->where('assessment_id', 2)->first()->passing_score);
    }

    // ============================================================
    // Relationships
    // ============================================================

    public function testSubtypeHasManyRelationship(): void
    {
        $this->createQuizRecord(['id' => 1, 'title' => 'Test Quiz'], ['assessment_id' => 1, 'passing_score' => 80]);

        DB::table('direct_quiz_questions')->insert([
            ['assessment_id' => 1, 'question_text' => 'Question 1', 'points' => 10],
            ['assessment_id' => 1, 'question_text' => 'Question 2', 'points' => 15],
        ]);

        $quiz = DirectQuiz::find(1);
        $questions = $quiz->questions;

        $this->assertCount(2, $questions);
        $this->assertEquals('Question 1', $questions[0]->question_text);
        $this->assertEquals('Question 2', $questions[1]->question_text);
    }

    public function testSubtypeHasManyWithFiltering(): void
    {
        $this->createQuizRecord(['id' => 1, 'title' => 'Test Quiz'], ['assessment_id' => 1, 'passing_score' => 80]);

        DB::table('direct_quiz_questions')->insert([
            ['assessment_id' => 1, 'question_text' => 'Easy Question', 'points' => 5],
            ['assessment_id' => 1, 'question_text' => 'Hard Question', 'points' => 20],
        ]);

        $quiz = DirectQuiz::find(1);
        $hardQuestions = $quiz->questions()->where('points', '>', 10)->get();

        $this->assertCount(1, $hardQuestions);
        $this->assertEquals('Hard Question', $hardQuestions[0]->question_text);
    }

    public function testSubtypeHasManyCreate(): void
    {
        $this->createQuizRecord(['id' => 1, 'title' => 'Test Quiz'], ['assessment_id' => 1, 'passing_score' => 80]);

        $quiz = DirectQuiz::find(1);
        $question = $quiz->questions()->create([
            'question_text' => 'New Question',
            'points' => 25,
        ]);

        $this->assertNotNull($question->id);
        $this->assertEquals(1, $question->assessment_id);
        $this->assertEquals('New Question', $question->question_text);
        $this->assertEquals(1, DB::table('direct_quiz_questions')->where('assessment_id', 1)->count());
    }

    public function testSubtypeHasManyCount(): void
    {
        $this->createQuizRecord(['id' => 1, 'title' => 'Test Quiz'], ['assessment_id' => 1, 'passing_score' => 80]);

        DB::table('direct_quiz_questions')->insert([
            ['assessment_id' => 1, 'question_text' => 'Question 1', 'points' => 10],
            ['assessment_id' => 1, 'question_text' => 'Question 2', 'points' => 15],
            ['assessment_id' => 1, 'question_text' => 'Question 3', 'points' => 20],
        ]);

        $quiz = DirectQuiz::find(1);
        $this->assertEquals(3, $quiz->questions()->count());
    }

    public function testSubtypeHasOneRelationship(): void
    {
        $this->createQuizRecord(['id' => 1, 'title' => 'Test Quiz'], ['assessment_id' => 1, 'passing_score' => 80]);

        DB::table('direct_quiz_settings')->insert([
            'assessment_id' => 1,
            'randomize_questions' => true,
            'show_progress_bar' => false,
        ]);

        $quiz = DirectQuiz::find(1);
        $settings = $quiz->settings;

        $this->assertNotNull($settings);
        $this->assertInstanceOf(DirectQuizSettings::class, $settings);
        $this->assertEquals(1, $settings->randomize_questions);
        $this->assertEquals(0, $settings->show_progress_bar);
    }

    public function testSubtypeHasOneReturnsNullWhenNoRelated(): void
    {
        $this->createQuizRecord(['id' => 1, 'title' => 'Test Quiz'], ['assessment_id' => 1, 'passing_score' => 80]);

        $quiz = DirectQuiz::find(1);
        $this->assertNull($quiz->settings);
    }

    public function testParentRelationshipAccessibleFromSubtype(): void
    {
        $this->createQuizRecord(['id' => 1, 'title' => 'Quiz with tags'], ['assessment_id' => 1, 'passing_score' => 85]);

        DB::table('direct_assessment_tag')->insert([
            ['assessment_id' => 1, 'tag_name' => 'math'],
            ['assessment_id' => 1, 'tag_name' => 'algebra'],
        ]);

        $quiz = DirectQuiz::find(1);
        $tags = $quiz->tags()->get();

        $this->assertCount(2, $tags);
        $this->assertEquals('math', $tags[0]->tag_name);
        $this->assertEquals('algebra', $tags[1]->tag_name);
    }

    public function testParentRelationshipQueryWorks(): void
    {
        $this->createQuizRecord(['id' => 1, 'title' => 'Quiz with tags'], ['assessment_id' => 1, 'passing_score' => 85]);

        DB::table('direct_assessment_tag')->insert([
            ['assessment_id' => 1, 'tag_name' => 'math'],
            ['assessment_id' => 1, 'tag_name' => 'algebra'],
            ['assessment_id' => 1, 'tag_name' => 'geometry'],
        ]);

        $quiz = DirectQuiz::find(1);

        $mathTags = $quiz->tags()->where('tag_name', 'math')->get();
        $this->assertCount(1, $mathTags);
        $this->assertEquals('math', $mathTags[0]->tag_name);
    }

    public function testSubtypeRelationshipTakesPrecedence(): void
    {
        $this->createQuizRecord(['id' => 1, 'title' => 'My Quiz'], ['assessment_id' => 1, 'passing_score' => 80]);

        DB::table('direct_quiz_questions')->insert([
            ['assessment_id' => 1, 'question_text' => 'Question 1', 'points' => 10],
            ['assessment_id' => 1, 'question_text' => 'Question 2', 'points' => 15],
        ]);

        $quiz = DirectQuiz::find(1);
        $questions = $quiz->questions;

        $this->assertCount(2, $questions);
        $this->assertInstanceOf(DirectQuizQuestion::class, $questions[0]);
    }

    public function testUndefinedMethodThrowsException(): void
    {
        $this->createQuizRecord(['id' => 1], ['assessment_id' => 1, 'passing_score' => 80]);

        $quiz = DirectQuiz::find(1);

        $this->expectException(\BadMethodCallException::class);
        $quiz->nonExistentMethod();
    }

    // ============================================================
    // Events
    // ============================================================

    public function testSubtypeEvents(): void
    {
        $events = [];

        DirectQuiz::saved(function ($quiz) use (&$events) {
            $events[] = 'saved';
        });

        DirectQuiz::subtypeSaved(function ($quiz) use (&$events) {
            $events[] = 'subtypeSaved';
        });

        $quiz = new DirectQuiz();
        $quiz->title = 'Test Quiz';
        $quiz->passing_score = 70;
        $quiz->time_limit = 60;
        $quiz->save();

        $this->assertContains('saved', $events);
        $this->assertContains('subtypeSaved', $events);
    }

    public function testSubtypeSavingEventCanHalt(): void
    {
        $eventFired = false;

        DirectQuiz::subtypeSaving(function ($quiz) use (&$eventFired) {
            $eventFired = true;
            return false;
        });

        $quiz = new DirectQuiz();
        $quiz->title = 'Test Quiz';
        $quiz->passing_score = 70;

        $result = $quiz->save();

        $this->assertTrue($eventFired);
        $this->assertFalse($result);
        $this->assertNull($quiz->id);
    }

    public function testSubtypeSavingEventAllowsSave(): void
    {
        $eventFired = false;

        DirectQuiz::subtypeSaving(function ($quiz) use (&$eventFired) {
            $eventFired = true;
            return true;
        });

        $quiz = new DirectQuiz();
        $quiz->title = 'Test Quiz';
        $quiz->passing_score = 70;

        $result = $quiz->save();

        $this->assertTrue($eventFired);
        $this->assertTrue($result);
        $this->assertNotNull($quiz->id);
    }

    public function testSubtypeDeletingEventCanHalt(): void
    {
        $this->createQuizRecord(['id' => 1, 'title' => 'Test Quiz'], ['assessment_id' => 1, 'passing_score' => 80]);

        $eventFired = false;

        DirectQuiz::subtypeDeleting(function ($quiz) use (&$eventFired) {
            $eventFired = true;
            return false;
        });

        $quiz = DirectQuiz::find(1);
        $result = $quiz->delete();

        $this->assertTrue($eventFired);
        $this->assertFalse($result);

        $this->assertNotNull(DB::table('direct_assessment')->where('id', 1)->first());
        $this->assertNotNull(DB::table('direct_assessment_quiz')->where('assessment_id', 1)->first());
    }

    public function testSubtypeDeletingEventAllowsDelete(): void
    {
        $this->createQuizRecord(['id' => 1, 'title' => 'Test Quiz'], ['assessment_id' => 1, 'passing_score' => 80]);

        $eventFired = false;

        DirectQuiz::subtypeDeleting(function ($quiz) use (&$eventFired) {
            $eventFired = true;
            return true;
        });

        $quiz = DirectQuiz::find(1);
        $result = $quiz->delete();

        $this->assertTrue($eventFired);
        $this->assertTrue($result);

        $this->assertNull(DB::table('direct_assessment')->where('id', 1)->first());
        $this->assertNull(DB::table('direct_assessment_quiz')->where('assessment_id', 1)->first());
    }

    public function testSubtypeDeletedEventFires(): void
    {
        $this->createQuizRecord(['id' => 1, 'title' => 'Test Quiz'], ['assessment_id' => 1, 'passing_score' => 80]);

        $eventFired = false;
        $deletedQuizId = null;

        DirectQuiz::subtypeDeleted(function ($quiz) use (&$eventFired, &$deletedQuizId) {
            $eventFired = true;
            $deletedQuizId = $quiz->id;
        });

        $quiz = DirectQuiz::find(1);
        $quiz->delete();

        $this->assertTrue($eventFired);
        $this->assertEquals(1, $deletedQuizId);
    }

    public function testSubtypeSavedEventFires(): void
    {
        $eventFired = false;
        $savedQuiz = null;

        DirectQuiz::subtypeSaved(function ($quiz) use (&$eventFired, &$savedQuiz) {
            $eventFired = true;
            $savedQuiz = $quiz;
        });

        $quiz = new DirectQuiz();
        $quiz->title = 'Test Quiz';
        $quiz->passing_score = 70;
        $quiz->save();

        $this->assertTrue($eventFired);
        $this->assertNotNull($savedQuiz);
        $this->assertEquals('Test Quiz', $savedQuiz->title);
        $this->assertEquals(70, $savedQuiz->passing_score);
    }

    public function testSubtypeSavingEventReceivesCorrectData(): void
    {
        $receivedQuiz = null;

        DirectQuiz::subtypeSaving(function ($quiz) use (&$receivedQuiz) {
            $receivedQuiz = $quiz;
        });

        $quiz = new DirectQuiz();
        $quiz->title = 'Test Quiz';
        $quiz->passing_score = 85;
        $quiz->time_limit = 120;
        $quiz->save();

        $this->assertNotNull($receivedQuiz);
        $this->assertEquals('Test Quiz', $receivedQuiz->title);
        $this->assertEquals(85, $receivedQuiz->passing_score);
        $this->assertEquals(120, $receivedQuiz->time_limit);
    }

    // ============================================================
    // Collection & Batch Loading
    // ============================================================

    public function testCollectionBatchLoadsSubtypeData(): void
    {
        $this->seedQuizAndSurvey();

        $now = date('Y-m-d H:i:s');
        DB::table('direct_assessment')->insert([
            ['id' => 3, 'type' => 'quiz', 'title' => 'Science Quiz', 'description' => null, 'enabled' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('direct_assessment_quiz')->insert([
            ['assessment_id' => 3, 'passing_score' => 70, 'time_limit' => 45],
        ]);

        $assessments = DirectAssessment::all();
        $this->assertCount(3, $assessments);

        $quiz1 = $assessments->firstWhere('id', 1);
        $quiz2 = $assessments->firstWhere('id', 3);
        $survey = $assessments->firstWhere('id', 2);

        $this->assertEquals(80, $quiz1->passing_score);
        $this->assertEquals(70, $quiz2->passing_score);
        $this->assertEquals(true, $survey->anonymous);
    }

    public function testMixedTypeBatchLoadingEfficiency(): void
    {
        $this->createQuizRecord(['id' => 1, 'title' => 'Quiz 1'], ['assessment_id' => 1, 'passing_score' => 80]);
        $this->createQuizRecord(['id' => 2, 'title' => 'Quiz 2'], ['assessment_id' => 2, 'passing_score' => 90]);
        $this->createSurveyRecord(['id' => 3, 'title' => 'Survey 1'], ['assessment_id' => 3, 'anonymous' => true]);

        DB::connection()->enableQueryLog();
        $assessments = DirectAssessment::all();
        $queryLog = DB::connection()->getQueryLog();

        $quizQueries = array_filter($queryLog, fn ($q) => strpos($q['query'], 'direct_assessment_quiz') !== false);
        $surveyQueries = array_filter($queryLog, fn ($q) => strpos($q['query'], 'direct_assessment_survey') !== false);

        $this->assertCount(1, $quizQueries, 'Should have exactly 1 query to direct_assessment_quiz');
        $this->assertCount(1, $surveyQueries, 'Should have exactly 1 query to direct_assessment_survey');

        $this->assertCount(3, $assessments);
        $quizzes = $assessments->filter(fn ($a) => $a instanceof DirectQuiz);
        $surveys = $assessments->filter(fn ($a) => $a instanceof DirectSurvey);
        $this->assertCount(2, $quizzes);
        $this->assertCount(1, $surveys);
    }

    public function testSubtypedCollectionWithEmptyCollection(): void
    {
        $collection = new \Pannella\Cti\Support\SubtypedCollection([]);

        $this->assertCount(0, $collection);
        $this->assertTrue($collection->isEmpty());
    }

    public function testSubtypedCollectionWithModelsWithoutKeys(): void
    {
        $model = new DirectQuiz();
        $model->title = 'Unsaved Quiz';
        $model->passing_score = 70;

        $collection = new \Pannella\Cti\Support\SubtypedCollection([$model]);

        $this->assertCount(1, $collection);
        $this->assertNull($collection->first()->id);
    }

    // ============================================================
    // Replicate and Refresh
    // ============================================================

    public function testReplicateQuiz(): void
    {
        $this->createQuizRecord(
            ['id' => 1, 'title' => 'Original Quiz'],
            ['assessment_id' => 1, 'passing_score' => 70, 'time_limit' => 30]
        );

        $original = DirectQuiz::find(1);
        $clone = $original->replicate();

        $this->assertTrue($clone->save());

        $this->assertNotEquals($original->id, $clone->id);
        $this->assertEquals($original->title, $clone->title);
        $this->assertEquals($original->passing_score, $clone->passing_score);
    }

    public function testReplicateWithExceptParameter(): void
    {
        $this->createQuizRecord(
            ['id' => 1, 'title' => 'Original Quiz'],
            ['assessment_id' => 1, 'passing_score' => 80, 'time_limit' => 60]
        );

        $original = DirectQuiz::find(1);
        $clone = $original->replicate(['time_limit']);

        $this->assertTrue($clone->save());
        $this->assertNotEquals($original->id, $clone->id);
        $this->assertEquals($original->title, $clone->title);
        $this->assertEquals($original->passing_score, $clone->passing_score);
        $this->assertNull($clone->time_limit);
    }

    public function testRefreshQuiz(): void
    {
        $this->createQuizRecord(
            ['id' => 1, 'title' => 'Test Quiz'],
            ['assessment_id' => 1, 'passing_score' => 70]
        );

        $quiz = DirectQuiz::find(1);

        DB::table('direct_assessment')->where('id', 1)->update(['title' => 'Updated Title']);
        DB::table('direct_assessment_quiz')->where('assessment_id', 1)->update(['passing_score' => 90]);

        $quiz->refresh();

        $this->assertEquals('Updated Title', $quiz->title);
        $this->assertEquals(90, $quiz->passing_score);
    }

    // ============================================================
    // Serialization
    // ============================================================

    public function testToArrayIncludesBothParentAndSubtypeAttributes(): void
    {
        $this->createQuizRecord(
            ['id' => 1, 'title' => 'Array Test', 'description' => 'A description'],
            ['assessment_id' => 1, 'passing_score' => 85, 'time_limit' => 60]
        );

        $quiz = DirectQuiz::find(1);
        $array = $quiz->toArray();

        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertEquals('Array Test', $array['title']);

        $this->assertArrayHasKey('passing_score', $array);
        $this->assertArrayHasKey('time_limit', $array);
        $this->assertEquals(85, $array['passing_score']);
        $this->assertEquals(60, $array['time_limit']);
    }

    public function testToJsonIncludesBothParentAndSubtypeAttributes(): void
    {
        $this->createQuizRecord(
            ['id' => 1, 'title' => 'JSON Test'],
            ['assessment_id' => 1, 'passing_score' => 85, 'time_limit' => 60]
        );

        $quiz = DirectQuiz::find(1);
        $json = $quiz->toJson();
        $decoded = json_decode($json, true);

        $this->assertArrayHasKey('title', $decoded);
        $this->assertArrayHasKey('passing_score', $decoded);
        $this->assertEquals('JSON Test', $decoded['title']);
        $this->assertEquals(85, $decoded['passing_score']);
    }

    // ============================================================
    // Casts
    // ============================================================

    public function testParentCastsAppliedToMorphedInstances(): void
    {
        $this->createQuizRecord(
            ['id' => 1, 'title' => 'Test Quiz', 'enabled' => 1],
            ['assessment_id' => 1, 'passing_score' => 80]
        );

        $quiz = DirectAssessment::find(1);

        $this->assertInstanceOf(DirectQuiz::class, $quiz);
        $this->assertIsBool($quiz->enabled);
        $this->assertTrue($quiz->enabled);
        $this->assertSame(true, $quiz->enabled);
    }

    public function testParentCastsWithFalseValues(): void
    {
        $this->createQuizRecord(
            ['id' => 1, 'title' => 'Test Quiz', 'enabled' => 0],
            ['assessment_id' => 1, 'passing_score' => 80]
        );

        $quiz = DirectAssessment::find(1);

        $this->assertInstanceOf(DirectQuiz::class, $quiz);
        $this->assertIsBool($quiz->enabled);
        $this->assertFalse($quiz->enabled);
        $this->assertSame(false, $quiz->enabled);
    }

    public function testDirectQueryAppliesParentCasts(): void
    {
        $timestamp = date('Y-m-d H:i:s');

        $this->createQuizRecord(
            ['id' => 1, 'title' => 'Quiz enabled=0', 'enabled' => 0, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['assessment_id' => 1, 'passing_score' => 80, 'time_limit' => 60]
        );
        $this->createQuizRecord(
            ['id' => 2, 'title' => 'Quiz enabled=1', 'enabled' => 1, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['assessment_id' => 2, 'passing_score' => 90, 'time_limit' => 45]
        );

        $quizzes = DirectQuiz::all();

        $quiz1 = $quizzes->first(fn ($q) => $q->id === 1);
        $quiz2 = $quizzes->first(fn ($q) => $q->id === 2);

        $this->assertIsBool($quiz1->enabled);
        $this->assertFalse($quiz1->enabled);

        $this->assertIsBool($quiz2->enabled);
        $this->assertTrue($quiz2->enabled);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $quiz1->created_at);
    }

    public function testSubtypeSpecificCasts(): void
    {
        $this->createQuizRecord(
            ['id' => 1, 'title' => 'Cast Test'],
            ['assessment_id' => 1, 'passing_score' => 85, 'time_limit' => 60]
        );

        $quiz = DirectQuiz::find(1);

        $this->assertIsInt($quiz->passing_score);
        $this->assertEquals(85, $quiz->passing_score);

        $this->assertIsInt($quiz->time_limit);
        $this->assertEquals(60, $quiz->time_limit);
    }

    // ============================================================
    // Fillable Inheritance
    // ============================================================

    public function testMinimalSubtypeInheritsParentFillable(): void
    {
        $quiz = new DirectMinimalQuiz();
        $fillable = $quiz->getFillable();

        // Should have parent fillable merged in
        $this->assertContains('type', $fillable);
        $this->assertContains('title', $fillable);
        $this->assertContains('description', $fillable);
        $this->assertContains('enabled', $fillable);
        // Plus own fillable
        $this->assertContains('passing_score', $fillable);
        $this->assertContains('time_limit', $fillable);
    }

    public function testNoInheritQuizDisablesFillableMerge(): void
    {
        $quiz = new DirectNoInheritQuiz();
        $fillable = $quiz->getFillable();

        // Should NOT have parent fillable
        $this->assertNotContains('type', $fillable);
        $this->assertNotContains('title', $fillable);
        $this->assertNotContains('description', $fillable);

        // Should still have own fillable
        $this->assertContains('passing_score', $fillable);
        $this->assertContains('time_limit', $fillable);
    }

    public function testExcludeFieldQuizExcludesSpecificAttrs(): void
    {
        $quiz = new DirectExcludeFieldQuiz();
        $fillable = $quiz->getFillable();

        // Should have most parent fillable
        $this->assertContains('type', $fillable);
        $this->assertContains('title', $fillable);
        $this->assertContains('enabled', $fillable);

        // But NOT description
        $this->assertNotContains('description', $fillable);

        // Should still have own fillable
        $this->assertContains('passing_score', $fillable);
        $this->assertContains('time_limit', $fillable);
    }

    public function testNoInheritQuizStillGetsCastsFromParent(): void
    {
        $quiz = new DirectNoInheritQuiz();
        $casts = $quiz->getCasts();

        // Parent cast for 'enabled' should still be inherited regardless of fillable setting
        $this->assertArrayHasKey('enabled', $casts);
        $this->assertEquals('boolean', $casts['enabled']);
    }

    public function testFillableProtection(): void
    {
        $data = [
            'title' => 'Test Quiz',
            'passing_score' => 70,
            'non_fillable_field' => 'should not be set',
        ];

        $quiz = new DirectQuiz();
        $quiz->fill($data);

        $this->assertEquals('Test Quiz', $quiz->title);
        $this->assertEquals(70, $quiz->passing_score);
        $this->assertNull($quiz->non_fillable_field);
    }

    // ============================================================
    // Pagination
    // ============================================================

    public function testPaginationWithSubtypeData(): void
    {
        if (!class_exists('\Illuminate\Pagination\Paginator')) {
            $this->markTestSkipped('Pagination package not installed');
        }

        for ($i = 1; $i <= 15; $i++) {
            $this->createQuizRecord(
                ['id' => $i, 'title' => "Quiz $i"],
                ['assessment_id' => $i, 'passing_score' => 60 + $i]
            );
        }

        $paginated = DirectQuiz::paginate(5);

        $this->assertCount(5, $paginated);
        $this->assertEquals(15, $paginated->total());
        $this->assertEquals(3, $paginated->lastPage());

        foreach ($paginated as $quiz) {
            $this->assertNotNull($quiz->passing_score);
            $this->assertGreaterThan(60, $quiz->passing_score);
        }
    }

    // ============================================================
    // Missing Subtype Data Strategies
    // ============================================================

    public function testMissingSubtypeDataLogStrategySetsFlagOnLoad(): void
    {
        $this->setCtiConfig('log');

        $now = date('Y-m-d H:i:s');
        DB::table('direct_assessment')->insert([
            'id' => 1,
            'type' => 'quiz',
            'title' => 'Quiz Without Subtype Data',
            'enabled' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $quiz = DirectQuiz::find(1);

        $this->assertNotNull($quiz);
        $this->assertEquals('Quiz Without Subtype Data', $quiz->title);
        $this->assertNull($quiz->passing_score);
        $this->assertTrue($quiz->isSubtypeDataMissing());
    }

    public function testMissingSubtypeDataExceptionStrategyThrowsOnLoad(): void
    {
        $this->setCtiConfig('exception');

        $now = date('Y-m-d H:i:s');
        DB::table('direct_assessment')->insert([
            'id' => 1,
            'type' => 'quiz',
            'title' => 'Quiz Without Subtype Data',
            'enabled' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->expectException(SubtypeException::class);
        DirectQuiz::find(1);
    }

    public function testMissingSubtypeDataNullStrategySilent(): void
    {
        $this->setCtiConfig('null');

        $now = date('Y-m-d H:i:s');
        DB::table('direct_assessment')->insert([
            'id' => 1,
            'type' => 'quiz',
            'title' => 'Quiz Without Subtype Data',
            'enabled' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $quiz = DirectQuiz::find(1);

        $this->assertNotNull($quiz);
        $this->assertNull($quiz->passing_score);
        $this->assertFalse($quiz->isSubtypeDataMissing());
    }

    // ============================================================
    // Overlapping Column Validation
    // ============================================================

    public function testOverlappingColumnsThrowOnSave(): void
    {
        $this->expectException(SubtypeException::class);
        $this->expectExceptionMessage('overlap with parent table columns: title');

        $model = new DirectOverlappingColumnsQuiz();
        $model->title = 'Test';
        $model->passing_score = 80;
        $model->save();
    }

    // ============================================================
    // Dirty Attributes
    // ============================================================

    public function testDirtyAttributesTracking(): void
    {
        $quiz = new DirectQuiz();

        $quiz->title = 'Test Quiz';
        $quiz->passing_score = 70;
        $this->assertTrue($quiz->isDirty());

        $quiz->save();
        $this->assertFalse($quiz->isDirty());

        $quiz->title = 'Updated Quiz';
        $this->assertTrue($quiz->isDirty('title'));
        $this->assertFalse($quiz->isDirty('passing_score'));

        $quiz->save();

        $quiz->passing_score = 80;
        $this->assertFalse($quiz->isDirty('title'));
        $this->assertTrue($quiz->isDirty('passing_score'));
    }

    // ============================================================
    // Primary Key and Timestamp Handling
    // ============================================================

    public function testPrimaryKeyHandling(): void
    {
        $quiz = new DirectQuiz();
        $quiz->title = 'Test Quiz';
        $quiz->passing_score = 70;
        $quiz->save();

        $this->assertNotNull($quiz->id);
        $this->assertEquals($quiz->id, $quiz->getKey());

        $subtypeRecord = DB::table('direct_assessment_quiz')
            ->where('assessment_id', $quiz->id)
            ->first();
        $this->assertNotNull($subtypeRecord);
    }

    public function testTimestampHandling(): void
    {
        $quiz = new DirectQuiz();
        $quiz->title = 'Test Quiz';
        $quiz->passing_score = 70;
        $quiz->save();

        $this->assertNotNull($quiz->created_at);
        $this->assertNotNull($quiz->updated_at);

        $originalUpdatedAt = $quiz->updated_at;
        sleep(1);

        $quiz->title = 'Updated Quiz';
        $quiz->save();

        $this->assertNotEquals($originalUpdatedAt, $quiz->updated_at);
    }

    // ============================================================
    // Mixed Subtypes
    // ============================================================

    public function testMixedSubtypesInDatabase(): void
    {
        $this->createQuizRecord(
            ['id' => 1, 'title' => 'My Quiz'],
            ['assessment_id' => 1, 'passing_score' => 80, 'time_limit' => 60]
        );

        $this->createSurveyRecord(
            ['id' => 2, 'title' => 'My Survey'],
            ['assessment_id' => 2, 'anonymous' => true, 'allow_multiple_responses' => true]
        );

        $quizzes = DirectQuiz::all();
        $this->assertCount(1, $quizzes);
        $quiz = $quizzes->first();
        $this->assertInstanceOf(DirectQuiz::class, $quiz);
        $this->assertEquals('My Quiz', $quiz->title);
        $this->assertEquals(80, $quiz->passing_score);

        $surveys = DirectSurvey::all();
        $this->assertCount(1, $surveys);
        $survey = $surveys->first();
        $this->assertInstanceOf(DirectSurvey::class, $survey);
        $this->assertEquals('My Survey', $survey->title);

        $assessments = DirectAssessment::all();
        $this->assertCount(2, $assessments);

        $quizAssessment = $assessments->first(fn ($a) => $a->title === 'My Quiz');
        $this->assertInstanceOf(DirectQuiz::class, $quizAssessment);
        $this->assertEquals(80, $quizAssessment->passing_score);

        $surveyAssessment = $assessments->first(fn ($a) => $a->title === 'My Survey');
        $this->assertInstanceOf(DirectSurvey::class, $surveyAssessment);
    }

    // ============================================================
    // getSubtypeLabel
    // ============================================================

    public function testGetSubtypeLabelDirectMode(): void
    {
        $this->seedQuizAndSurvey();

        $quiz = DirectQuiz::find(1);
        $this->assertEquals('quiz', $quiz->getSubtypeLabel());
    }

    // ============================================================
    // Attribute-based config
    // ============================================================

    public function testAttributeConfigDirectModeCreatesCorrectly(): void
    {
        $quiz = new AttributeDirectQuiz();
        $quiz->title = 'Attr Quiz';
        $quiz->passing_score = 75;
        $quiz->save();

        $this->assertEquals('quiz', $quiz->type);

        $record = DB::table('direct_assessment')->where('id', $quiz->id)->first();
        $this->assertEquals('quiz', $record->type);
    }

    public function testAttributeConfigDirectModeLoadsCorrectly(): void
    {
        $this->seedQuizAndSurvey();

        $assessments = AttributeDirectAssessment::all();
        $this->assertCount(2, $assessments);
        $this->assertInstanceOf(AttributeDirectQuiz::class, $assessments[0]);
        $this->assertInstanceOf(AttributeDirectSurvey::class, $assessments[1]);
    }

    public function testAttributeConfigDirectModeQueryScope(): void
    {
        $this->seedQuizAndSurvey();

        $quizzes = AttributeDirectQuiz::all();
        $this->assertCount(1, $quizzes);
        $this->assertEquals('Math Quiz', $quizzes->first()->title);
    }

    public function testAttributeConfigUsesLookupTableReturnsFalse(): void
    {
        $model = new AttributeDirectAssessment();
        $this->assertFalse($model->usesLookupTable());
        $this->assertNull($model->getSubtypeLookupTable());
        $this->assertNull($model->getSubtypeLookupKey());
        $this->assertNull($model->getSubtypeLookupLabel());
    }

    // ============================================================
    // No Duplicate Queries
    // ============================================================

    public function testNoDuplicateQueriesWhenMorphing(): void
    {
        $this->createQuizRecord(['id' => 1, 'title' => 'Quiz 1', 'enabled' => 1], ['assessment_id' => 1, 'passing_score' => 80]);
        $this->createQuizRecord(['id' => 2, 'title' => 'Quiz 2', 'enabled' => 1], ['assessment_id' => 2, 'passing_score' => 90]);

        DB::connection()->enableQueryLog();
        $assessments = DirectAssessment::all();
        $queryLog = DB::connection()->getQueryLog();

        $subtypeQueries = array_filter($queryLog, function ($query) {
            return strpos($query['query'], 'direct_assessment_quiz') !== false;
        });

        $this->assertCount(1, $subtypeQueries, 'Should only have 1 query to direct_assessment_quiz table');

        $this->assertCount(2, $assessments);
        $this->assertInstanceOf(DirectQuiz::class, $assessments[0]);
        $this->assertInstanceOf(DirectQuiz::class, $assessments[1]);
        $this->assertEquals(80, $assessments[0]->passing_score);
        $this->assertEquals(90, $assessments[1]->passing_score);
    }
}
