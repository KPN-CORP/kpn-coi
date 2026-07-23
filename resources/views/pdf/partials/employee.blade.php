<table class="mt-2">

<tr>

<td width="120">

{{ $locale === 'id' ? 'Nama' : 'Name' }}

</td>

<td width="15">

:

</td>

<td>

{{ $declaration->declarantName() }}

</td>

</tr>

<tr>

<td>

{{ $locale === 'id' ? 'Nomor Induk Kependudukan   
(NIK)' : 'ID Number' }}

</td>

<td>

:

</td>

<td>

{{ $declaration->declarantIdNumber() }}

</td>

</tr>

<tr>

<td>

{{ $locale === 'id' ? 'Alamat Sesuai Identitas' : 'Address' }}

</td>

<td>

:

</td>

<td>

{{ $declaration->declarantAddress() }}

</td>

</tr>

</table>

<p class="mt-3">

{{ $locale === 'id' ? 'menyatakan bahwa pada saat “Deklarasi Konflik Kepentingan” ini ditandatangani:' : '
hereby declare that at the time this
"Conflict of Interest Declaration"
is signed:' }} 

</p>