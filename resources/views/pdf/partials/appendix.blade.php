@php
    $questions = config('coi.questions');

    $responses = $declaration->responses->keyBy('question_key');
@endphp

@foreach($questions as $question)

    @php
        $response = $responses->get($question['key']);

        $details = $response?->response_value['details'] ?? [];
    @endphp

    @continue(empty($details))

    <div class="page-break"></div>

    <div class="title-2 text-center">
        <div>
            {{ $question['appendix_title']['id'] }}
        </div>

        <div>
            {{ $question['appendix_subtitle']['id'] }}
        </div>

        <div>
            <em>{{ $question['appendix_title']['en'] }}</em>
        </div>

        <div>
            <em>{{ $question['appendix_subtitle']['en'] }}</em>
        </div>
    </div>

    @foreach($details as $detail)

        <table class="field-table mt-4">

            @foreach($question['fields'] as $field)

                <tr>
                    <td width="220">
                        <strong>{{ $field['label']['id'] }}</strong>
                        <br>
                        <em>{{ $field['label']['en'] }}</em>
                    </td>

                    <td>
                        : {{ data_get($detail, $field['key'], '') }}
                    </td>
                </tr>

            @endforeach

        </table>

        @unless($loop->last)
            <br><br><br>
        @endunless

    @endforeach

@endforeach