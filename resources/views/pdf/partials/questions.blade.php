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

                    @php
                        $detail = $value['details'][0];
                    @endphp

                    <table width="100%" style="margin-top:8px;">

                        @foreach($question['fields'] as $field)

                            <tr>

                                <td width="180">

                                    {{ $field['label'][$locale] ?? $field['label']['en'] }}

                                </td>

                                <td>

                                    : {{ data_get($detail, $field['key'], '-') }}

                                </td>

                            </tr>

                        @endforeach

                    </table>

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