@php
    $footer = config('coi.pdf_footer');
@endphp

@foreach($footer['paragraphs'] as $paragraph)

    <p class="{{ $loop->first ? 'mt-4' : 'mt-3' }}" style="text-align: justify;">
        {{ $paragraph[$locale] ?? $paragraph['en'] }}
    </p>

@endforeach
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Ephesis&display=swap" rel="stylesheet">
<style>
    
.ephesis-regular {
  font-family: "Ephesis", cursive;
  font-weight: 400;
  font-style: normal;
  font-size: 22pt;
}

</style>
<div class="signature">
    <span
        class="ephesis-regular"
    >
        {{ $declaration->user->employee->fullname }}
    </span>

    <br>
    {{ $locale === 'id' ? 'Di Deklarasikan oleh' : 'Declared by' }} :
    {{ $declaration->user->employee->fullname }}
    <br>
    {{ $locale === 'id' ? 'Tanggal' : 'Date' }} :
    {{ $declaration->created_at->format('d/m/Y') }}

</div>