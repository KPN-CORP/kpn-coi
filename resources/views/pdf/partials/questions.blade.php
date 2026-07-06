@php

$questions = config('coi.questions');
$responses = $declaration->responses->keyBy('question_key');
$yesLabel = $locale === 'id' ? 'Ya' : 'Yes';
$noLabel = $locale === 'id' ? 'Tidak' : 'No';
@endphp
<table class="border mt-3">

    <thead>

    <tr>

    <th width="20">

    No

    </th>

    <th>

    {{ $locale === 'id' ? 'Potensi Konflik Kepentingan' : 'Conflict of Interest Potential' }}

    </th>

    <th width="40">

    {{ $locale === 'id' ? 'Ya/Tidak' : 'Yes/No' }} 

    </th>

    </tr>

    </thead>

    <tbody>

    @foreach($questions as $question)

        @php
            $response = $responses->get($question['key']);

            $value = $response?->response_value ?? [
                'answer' => false,
                'details' => [],
            ];
        @endphp

        <tr>
            <td style="text-align: center; vertical-align: top;">
                {{ $loop->iteration }}
            </td>

            <td>

                {{ $question['title'][$locale] ?? $question['title']['en'] }}

                @if($value['answer'] && count($value['details']))

                    <br><br>

                    <p style="font-style: italic">{{ $locale === 'id' ? 'Jika Ya, mohon jelaskan:' : 'If Yes, please specify:' }}</p>

                    @foreach($value['details'] as $index => $detail)

                        @if($index > 0)
                            <hr style="margin:10px 0;">
                        @endif

                        <table width="100%" style="margin-top:8px;">

                            @foreach($question['fields'] as $field)

                                @php

                                    $display = '-';

                                    if (($field['type'] ?? 'text') === 'date_range') {

                                        $from = data_get(
                                            $detail,
                                            $field['key'].'_from'
                                        );

                                        $to = data_get(
                                            $detail,
                                            $field['key'].'_to'
                                        );

                                        $display =
                                            $from && $to
                                                ? \Carbon\Carbon::parse($from)->format('d/m/Y')
                                                    .' - '.
                                                \Carbon\Carbon::parse($to)->format('d/m/Y')
                                                : '-';

                                    } elseif (($field['type'] ?? 'text') === 'select') {

                                        $valueSelected = data_get(
                                            $detail,
                                            $field['key']
                                        );

                                        $option = collect(
                                            $field['options'] ?? []
                                        )->firstWhere(
                                            'value',
                                            $valueSelected
                                        );

                                        $display =
                                            $option['label'][$locale]
                                            ?? $option['label']['en']
                                            ?? '-';

                                    } else {

                                        $display = data_get(
                                            $detail,
                                            $field['key'],
                                            '-'
                                        );

                                    }

                                @endphp

                                <tr>

                                    <td width="220">

                                        {{ $field['label'][$locale] ?? $field['label']['en'] }}

                                    </td>

                                    <td>

                                        : {{ $display }}

                                    </td>

                                </tr>

                                {{-- Conditional fields (requires) --}}

                                @if(($field['type'] ?? '') === 'select')

                                    @php

                                        $selected = collect(
                                            $field['options'] ?? []
                                        )->firstWhere(
                                            'value',
                                            data_get($detail, $field['key'])
                                        );

                                    @endphp

                                    @foreach($selected['requires'] ?? [] as $required)

                                        <tr>

                                            <td width="220">

                                                {{ $required['label'][$locale] ?? $required['label']['en'] }}

                                            </td>

                                            <td>

                                                : {{ data_get($detail, $required['key'], '-') }}

                                            </td>

                                        </tr>

                                    @endforeach

                                @endif

                            @endforeach

                        </table>

                    @endforeach

                    @if(count($value['details']) > 1)

                        <p style="margin-top:8px;">
                            <em>
                                Additional information is provided in the Appendix.
                            </em>
                        </p>

                    @endif

                @endif

            </td>

            <td style="vertical-align: top;">

                {{ $value['answer'] ? "☑ {$yesLabel}" : "☐ {$yesLabel}" }}

                <br>

                {{ !$value['answer'] ? "☑ {$noLabel}" : "☐ {$noLabel}" }}

            </td>
        </tr>

    @endforeach

    </tbody>

</table>