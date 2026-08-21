<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Скидки");
?><?$APPLICATION->IncludeComponent(
	"bitrix:furniture.catalog.random",
	"",
	Array(
		"CACHE_GROUPS" => "Y",
		"CACHE_TIME" => "180",
		"CACHE_TYPE" => "A",
		"DETAIL_URL" => "",
		"IBLOCKS" => array("2"),
		"IBLOCK_TYPE" => "catalog",
		"PARENT_SECTION" => ""
	)
);?><?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>