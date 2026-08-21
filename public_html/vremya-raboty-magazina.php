<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Время работы магазина");
?><table cellpadding="5" cellspacing="1" border="1" style="width: 400px; height: 400px;" align="center">
<tbody>
<tr style="border: 1px solid #000000;">
	<th>
		 &nbsp;День недели
	</th>
	<th>
		 &nbsp;Время работы
	</th>
</tr>
<tr>
	<td style="background-color: #cccccc;">
 <span style="font-family: Verdana; font-size: 13pt;">&nbsp;Понедельник </span>
	</td>
	<td style="background-color: #cccccc;">
 <span style="font-family: Verdana; font-size: 13pt;">
		<p style="text-align: center;">
			 08:00 - 20:00
		</p>
 </span>
	</td>
</tr>
<tr>
	<td>
 <span style="font-family: Verdana; font-size: 13pt;">
		&nbsp;Вторник </span>
	</td>
	<td>
 <span style="font-family: Verdana; font-size: 13pt;">
		<p style="text-align: center;">
			 08:00 - 20:00
		</p>
 </span>
	</td>
</tr>
<tr>
	<td style="background-color: #cccccc;">
 <span style="font-family: Verdana; font-size: 13pt;">
		&nbsp;Среда </span>
	</td>
	<td style="background-color: #cccccc;">
 <span style="font-family: Verdana; font-size: 13pt;">
		<p style="text-align: center;">
			 08:00 - 20:00
		</p>
 </span>
	</td>
</tr>
<tr>
	<td>
 <span style="font-family: Verdana; font-size: 13pt;">
		&nbsp;Четверг </span>
	</td>
	<td>
 <span style="font-family: Verdana; font-size: 13pt;">
		<p style="text-align: center;">
			 08:00 - 20:00
		</p>
 </span>
	</td>
</tr>
<tr>
	<td style="background-color: #cccccc;">
 <span style="font-family: Verdana; font-size: 13pt;">
		&nbsp;Пятница </span>
	</td>
	<td style="background-color: #cccccc;">
 <span style="font-family: Verdana; font-size: 13pt;">
		<p style="text-align: center;">
			 08:00 - 20:00
		</p>
 </span>
	</td>
</tr>
<tr>
	<td>
 <span style="font-family: Verdana; font-size: 13pt;">
		&nbsp;Суббота </span>
	</td>
	<td rowspan="2" style="background-color: #a4d49d;">
 <span style="font-family: Verdana; font-size: 13pt;">
		&nbsp; &nbsp;
		<p style="text-align: center;">
			 Выходной
		</p>
 </span>
	</td>
</tr>
<tr>
	<td colspan="1" style="background-color: #cccccc;">
 <span style="font-family: Verdana; font-size: 13pt;">
		&nbsp;Воскресенье</span>
	</td>
</tr>
</tbody>
</table>
 <br><?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>