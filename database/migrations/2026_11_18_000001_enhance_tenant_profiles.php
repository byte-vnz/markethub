<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class EnhanceTenantProfiles extends Migration
{
    public function up()
    {
        Schema::table('tenants', function (Blueprint $t) {
            foreach (['last_name','first_name','middle_name','middle_initial','civil_status','gender','previous_account_number','present_account_number','source_individual_sn','source_name_sn','government_id_type','government_id_place_issued'] as $field) {
                $t->string($field)->nullable();
            }
            $t->string('citizenship')->default('Filipino');
            $t->text('government_id_number')->nullable();
            $t->date('government_id_date_issued')->nullable();
            $t->text('government_id_issuance_exception_reason')->nullable();
        });
        Schema::table('stall_assignments', function (Blueprint $t) {
            $t->unsignedInteger('source_stall_count')->nullable();
            $t->unsignedInteger('registered_stall_count')->nullable();
            $t->string('source_sequence')->nullable();
        });
        Schema::create('tenant_helpers', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->foreignId('tenant_id')->constrained();
            $t->foreignId('stall_assignment_id')->constrained();
            $t->unsignedTinyInteger('slot');
            $t->string('full_name');
            $t->string('relationship')->nullable();
            $t->string('phone',40)->nullable();
            $t->string('status',20)->default('authorized');
            $t->text('remarks')->nullable();
            $t->unique(['stall_assignment_id','slot']);
            $t->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('tenant_helpers');
        Schema::table('stall_assignments', function (Blueprint $t) {
            $t->dropColumn(['source_stall_count','registered_stall_count','source_sequence']);
        });
        Schema::table('tenants', function (Blueprint $t) {
            $t->dropColumn(['last_name','first_name','middle_name','middle_initial','civil_status','gender','citizenship','previous_account_number','present_account_number','source_individual_sn','source_name_sn','government_id_type','government_id_number','government_id_date_issued','government_id_place_issued','government_id_issuance_exception_reason']);
        });
    }
}
