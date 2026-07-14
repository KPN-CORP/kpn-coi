@php
    $footer = config('coi.pdf_footer');
@endphp

@foreach($footer['paragraphs'] as $paragraph)

    <p class="{{ $loop->first ? 'mt-4' : 'mt-3' }}" style="text-align: justify;">
        {{ $paragraph[$locale] ?? $paragraph['en'] }}
    </p>

@endforeach

{{-- <div class="signature">

    <br><br><br><br>
    <span
        class="ephesis"
        style="font-size:22pt;"
    >
        {{ $declaration->user->employee->fullname }}
    </span>

    <br>
    {{ $locale === 'id' ? 'Di Deklarasikan oleh,' : 'Declared by,' }}
    {{ $declaration->user->employee->fullname }}
    <br>
    {{ $locale === 'id' ? 'Tanggal' : 'Date' }} :
    {{ $declaration->created_at->format('d F Y') }}

</div> --}}