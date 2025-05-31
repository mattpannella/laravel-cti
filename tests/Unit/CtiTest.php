<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Pannella\Cti\Traits\HasSubtypes;
use Pannella\Cti\SubtypedModel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CtiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup schema for testing
        Schema::create('assessment_types', function (Blueprint $table) {
            $table->increments('id');
            $table->string('label');
        });

        Schema::create('assessments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('type_id')->nullable();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('assessment_quiz', function (Blueprint $table) {
            $table->unsignedInteger('assessment_id')->primary();
            $table->string('quiz_specific_field')->nullable();
        });

        Schema::create('assessment_survey', function (Blueprint $table) {
            $table->unsignedInteger('assessment_id')->primary();
            $table->string('survey_specific_field')->nullable();
        });

        // Seed type labels
        \DB::table('assessment_types')->insert([
            ['id' => 1, 'label' => 'quiz'],
            ['id' => 2, 'label' => 'survey'],
        ]);

        // Clear model cache if any
        \Illuminate\Database\Eloquent\Model::unguard();
    }

    /** 
     * Define your base and subtype models inline for testing 
     */
    public function test_cti_models()
    {
        // Base Assessment model with HasSubtypes trait
        $assessmentClass = new class extends \Illuminate\Database\Eloquent\Model {
            use HasSubtypes;

            protected $table = 'assessments';
            protected $fillable = ['id', 'type_id', 'title'];
            protected static $subtypeMap = [
                'quiz' => Quiz::class,
                'survey' => Survey::class,
            ];
            protected static $subtypeKey = 'type_id';
            protected static $subtypeLookupTable = 'assessment_types';
            protected static $subtypeLookupKey = 'id';
            protected static $subtypeLookupLabel = 'label';
        };

        // Subtype Quiz model extending SubtypedModel
        $quizClass = new class extends SubtypedModel {
            protected $table = 'assessments';
            protected $subtypeTable = 'assessment_quiz';
            protected $subtypeAttributes = ['quiz_specific_field'];
            protected $fillable = ['id', 'type_id', 'title', 'quiz_specific_field'];
        };

        // Subtype Survey model extending SubtypedModel
        $surveyClass = new class extends SubtypedModel {
            protected $table = 'assessments';
            protected $subtypeTable = 'assessment_survey';
            protected $subtypeAttributes = ['survey_specific_field'];
            protected $fillable = ['id', 'type_id', 'title', 'survey_specific_field'];
        };

        // Bind classes to variables for use in closures
        $Assessment = $assessmentClass;
        $Quiz = $quizClass;
        $Survey = $surveyClass;

        // Save classes globally so HasSubtypes can find them (hack for inline test)
        class_alias(get_class($Quiz), 'Quiz');
        class_alias(get_class($Survey), 'Survey');

        // Create a quiz record
        $quiz = new $Quiz([
            'type_id' => 1,
            'title' => 'Quiz Title',
            'quiz_specific_field' => 'Quiz Extra',
        ]);
        $quiz->save();

        // Create a survey record
        $survey = new $Survey([
            'type_id' => 2,
            'title' => 'Survey Title',
            'survey_specific_field' => 'Survey Extra',
        ]);
        $survey->save();

        // Fetch all assessments and check morphing works
        $all = $Assessment::all()->loadSubtypes();

        $this->assertCount(2, $all);

        $first = $all->first();
        $this->assertInstanceOf(get_class($Quiz), $first);
        $this->assertEquals('Quiz Title', $first->title);
        $this->assertEquals('Quiz Extra', $first->quiz_specific_field);

        $last = $all->last();
        $this->assertInstanceOf(get_class($Survey), $last);
        $this->assertEquals('Survey Title', $last->title);
        $this->assertEquals('Survey Extra', $last->survey_specific_field);

        // Test update
        $first->title = 'Updated Quiz Title';
        $first->quiz_specific_field = 'Updated Quiz Extra';
        $first->save();

        $updated = $Assessment::find($first->id)->loadSubtypes()->first();
        $this->assertEquals('Updated Quiz Title', $updated->title);
        $this->assertEquals('Updated Quiz Extra', $updated->quiz_specific_field);

        // Test delete
        $id = $last->id;
        $last->delete();
        $this->assertNull($Assessment::find($id));
        $this->assertNull(\DB::table('assessment_survey')->where('assessment_id', $id)->first());
    }
}
