<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Курс валют");
?><?$APPLICATION->IncludeComponent(
	"bitrix:currency.rates", 
	".default", 
	[
		"CACHE_TIME" => "86400",
		"CACHE_TYPE" => "A",
		"CURRENCY_BASE" => "RUB",
		"RATE_DAY" => "2026-01-01",
		"SHOW_CB" => "Y",
		"arrCURRENCY_FROM" => [
			0 => "USD",
			1 => "EUR",
		],
		"COMPONENT_TEMPLATE" => ".default"
	],
	false
);?><?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>