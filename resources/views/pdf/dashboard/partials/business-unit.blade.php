<h3>{{ $barChart['title'] }}</h3>

<table border="1"
cellpadding="6"
style="margin-top:10px;">
    <thead>
        <tr style="background:#eeeeee;"
>
            <th>Category</th>

            @foreach($barChart['datasets'] as $dataset)
                <th>{{ $dataset['label'] }}</th>
            @endforeach
        </tr>
    </thead>

    <tbody>
        @foreach($barChart['labels'] as $index => $label)
            <tr>
                <td>{{ $label }}</td>

                @foreach($barChart['datasets'] as $dataset)
                    <td>{{ $dataset['data'][$index] ?? 0 }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>