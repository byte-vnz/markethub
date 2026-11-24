<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call(RoleSeeder::class);
        $this->call(BusinessCategorySeeder::class);
        $this->call(DefaultUserSeeder::class);
    }
}
