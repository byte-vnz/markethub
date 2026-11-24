<?php
return ['scheduled_drafts'=>(bool)env('BILLING_SCHEDULED_DRAFTS',false),'actor'=>env('BILLING_ACTOR_ID'),'due_day'=>(int)env('BILLING_DUE_DAY',0)];
