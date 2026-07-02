<h3>

Submission by Business Unit

</h3>

<table
border="1"
cellpadding="6"
style="margin-top:10px;"
>

<thead>

<tr
style="background:#eeeeee;"
>

<th>
Business Unit
</th>

<th width="100">
Submitted
</th>

<th width="100">
Pending
</th>

</tr>

</thead>

<tbody>

@foreach($businessUnits as $item)

<tr>

<td>

{{ $item['label'] }}

</td>

<td align="center">

{{ $item['submitted'] }}

</td>

<td align="center">

{{ $item['pending'] }}

</td>

</tr>

@endforeach

</tbody>

</table>

<br>