<h3 style="margin-bottom:15px;">
Dashboard Visualization
</h3>

<table>

<tr>

<td width="30%" align="center">

<strong>
Overall Status
</strong>

<br><br>

@if(!empty($statusChart))
    <img src="{{ $statusChart }}" style="width:200px; height:200px;">
@endif

</td>

<td width="70%" align="center">

<strong>
Submission by Business Unit
</strong>

<br><br>

@if(!empty($businessUnitChart))
    <img src="{{ $businessUnitChart }}" style="width:450px; height:300px;">
@endif

</td>

</tr>

</table>

<br>
<hr>
<br>