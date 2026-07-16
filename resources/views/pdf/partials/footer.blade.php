@php
    $footer = config('coi.pdf_footer');

    $footerDate = $declaration->created_at
        ->copy()
        ->timezone('Asia/Jakarta')
        ->locale($locale === 'id' ? 'id' : 'en');
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
    {{ $footerDate->translatedFormat('d F Y') }}
    {{ $locale === 'id' ? 'Pukul' : 'at' }}
    {{ $footerDate->format('H:i:s') }}

</div> --}}