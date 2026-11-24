<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantRegistrationSubmissions extends Migration
{
    public function up()
    {
        Schema::create('tenant_registration_submissions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->foreignId('actor_id')->constrained('users');
            $table->uuid('submission_key');
            $table->char('request_hash', 64);
            $table->foreignId('tenant_id')->nullable()->constrained();
            $table->timestamp('created_at');
            $table->primary(['actor_id', 'submission_key'], 'tenant_registration_submissions_pk');
        });
    }

    public function down()
    {
        if (Schema::hasTable('tenant_registration_submissions') && \Illuminate\Support\Facades\DB::table('tenant_registration_submissions')->exists()) {
            throw new \LogicException('Retain registration submission keys. Use a corrective forward migration instead of erasing replay protection.');
        }
        Schema::dropIfExists('tenant_registration_submissions');
    }
}
