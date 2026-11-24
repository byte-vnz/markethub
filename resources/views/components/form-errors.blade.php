@if($errors->any())
    <div id="form-errors" role="alert" tabindex="-1" class="notice error">
        <p class="font-semibold">Please check the following:</p>
        <ul class="list-disc pl-5">
            @foreach($errors->getMessages() as $field => $messages)
                @php($target = $fieldIds[$field] ?? trim($__env->yieldPushContent('error-target-'.$field)))
                @foreach($messages as $error)
                    <li>@if($target !== '')<a class="underline" href="#{{ $target }}">{{ $error }}</a>@else{{ $error }}@endif</li>
                @endforeach
            @endforeach
        </ul>
    </div>
@endif
