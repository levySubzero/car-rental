{{-- Brand mail layout (M10.1): company name in the header, contacts in the footer. --}}
@php($company = app(\App\Settings\GeneralSettings::class))
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ $company->company_name }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
{{ $company->legal_name ?: $company->company_name }}@if ($company->address)<br>{{ $company->address }}@endif
@if ($company->phones !== [])<br>{{ implode(', ', $company->phones) }}@endif
@if ($company->email)<br>{{ $company->email }}@endif
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
