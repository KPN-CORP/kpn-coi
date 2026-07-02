@php
    $footer = config('coi.pdf_footer');
@endphp

@foreach($footer['paragraphs'] as $paragraph)

    <p class="{{ $loop->first ? 'mt-4' : 'mt-3' }}" style="text-align: justify;">
        {{ $paragraph[$locale] ?? $paragraph['en'] }}
    </p>

@endforeach

<div class="signature">

    {{ $locale === 'id' ? 'Dibuat oleh,' : 'Declared by,' }}

    <br><br><br><br>
    {{ $locale === 'id' ? 'Nama Lengkap' : 'Full Name' }} :
    <strong>
        {{ $declaration->user->employee->fullname }}
    </strong>

    <br>

    {{ $locale === 'id' ? 'Tanggal' : 'Date' }} :
    {{ $declaration->created_at->format('d F Y') }}

</div>