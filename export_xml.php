<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Title");
?>
<?php
	if(CModule::IncludeModule("iblock"))
	{
		$arSelect = Array(
			'ID',
			'ACTIVE',
			'NAME',
			'PROPERTY_ADDRESS',
			'PROPERTY_PHONE',
			'PROPERTY_HOURS',
			'PROPERTY_N',
			'PROPERTY_E',
			'PROPERTY_WIFI',
			'PROPERTY_CARDS',
			'PROPERTY_TRUCK'
		);
	
		$arFilter = Array(
			"IBLOCK_ID"=>IntVal('16'),
			"ACTIVE" => "Y"
		);
	
		$res = CIBlockElement::GetList(Array("SORT"=>"ASC"), $arFilter, $arSelect);

		$dom = new domDocument("1.0", "utf-8");
		$dom->formatOutput = true;
		
		$root = $dom->createElement("companies");
		$dom->appendChild($root);
				
		while($ar_fields = $res->GetNext())
		{
			$reg_phone = '/[+7|8][0-9\s)(\-]{0,}/m';
			preg_match_all($reg_phone, $ar_fields["PROPERTY_PHONE_VALUE"]["TEXT"], $phones, PREG_SET_ORDER, 0);
			
			$reg_hourse = '/(^(Шинный центр:)|^(.*))\s?([а-я0-9\.\-:\s,]*)/u';
			preg_match_all($reg_hourse, $ar_fields["PROPERTY_HOURS_VALUE"]["TEXT"], $hourses, PREG_SET_ORDER, 0);
			
			$company = $dom->createElement("company");
			
			$center_id = $dom->createElement("company-id", trim($ar_fields["ID"]));
			$name = $dom->createElement("name", "***");
			$name->setAttribute("lang", "ru");
			
			$address = $dom->createElement("address", $ar_fields["PROPERTY_ADDRESS_VALUE"]);
			$address->setAttribute("lang", "ru");
			
			$country = $dom->createElement("country", "Россия");
			$country->setAttribute("lang", "ru");
			
			$address_add = $dom->createElement("address-add", $ar_fields["NAME"]);
			$address_add->setAttribute("lang", "ru");
			
			$company->appendChild($center_id); 
			$company->appendChild($name); 
			$company->appendChild($address); 
			$company->appendChild($country); 
			$company->appendChild($address_add); 			
			
			foreach($phones as $value_num) {
				$phone = $dom->createElement("phone");
					$number = $dom->createElement("number", $value_num[0]);
					$type = $dom->createElement("type", 'phone');
					
				$phone->appendChild($number);
				$phone->appendChild($type);
				
				$company->appendChild($phone); 
			}
			
			
			$email = $dom->createElement("email", "***");
			
			$url = $dom->createElement("url", "***" . trim($ar_fields["ID"]));
			$add_url = $dom->createElement("add-url", "***");
			$info_page = $dom->createElement("info-page", "***");
			
			$company->appendChild($email); 
			$company->appendChild($url); 
			$company->appendChild($add_url); 
			$company->appendChild($info_page); 			
			
			foreach($hourses as $value_hour) {			
				$working_time = $dom->createElement("working-time", str_replace("Шинный центр: ", "", $value_hour[0]));
				$working_time->setAttribute("lang", "ru");
				
				$company->appendChild($working_time);
			}
			
			$rubric_id = $dom->createElement("rubric-id", "184105298");
			$rubric_id1 = $dom->createElement("rubric-id", "184105260");
			$rubric_id2 = $dom->createElement("rubric-id", "184105246");
			
				$coordinates = $dom->createElement("coordinates");
					$lat = $dom->createElement("lat", $ar_fields["PROPERTY_N_VALUE"]);
					$lon = $dom->createElement("lon", $ar_fields["PROPERTY_E_VALUE"]);
					$coordinates->appendChild($lat); 
					$coordinates->appendChild($lon); 
					
			$actualization_date = $dom->createElement("actualization-date", time());
		
		$feature_boolean_tf = $dom->createElement("feature-boolean");
		$feature_boolean_tf->setAttribute("name", "tire_fitting");
		$feature_boolean_tf->setAttribute("value", "1");
		
			$company->appendChild($rubric_id);
			$company->appendChild($rubric_id1);
			$company->appendChild($rubric_id2);
			$company->appendChild($coordinates);
			$company->appendChild($actualization_date);
			$company->appendChild($feature_boolean_tf);
		
		if($ar_fields["PROPERTY_TRUCK_VALUE"] == 'Y') {
			$feature_boolean_ts = $dom->createElement("feature-boolean");
			$feature_boolean_ts->setAttribute("name", "truck_tire_service");
			$feature_boolean_ts->setAttribute("value", "1");
			
			$company->appendChild($feature_boolean_ts);
		}
		
		if($ar_fields["PROPERTY_CARDS_VALUE"] == 'Y') {
			$feature_boolean_pb = $dom->createElement("feature-boolean");
			$feature_boolean_pb->setAttribute("name", "payment_by_credit_card");
			$feature_boolean_pb->setAttribute("value", "1");
			
			$company->appendChild($feature_boolean_pb);			
		}

		if($ar_fields["PROPERTY_WIFI_VALUE"] == 'Y') {
			$feature_boolean_wf = $dom->createElement("feature-boolean");
			$feature_boolean_wf->setAttribute("name", "wi_fi");
			$feature_boolean_wf->setAttribute("value", "1");	
			
			$company->appendChild($feature_boolean_wf);
		}

			$root->appendChild($company);
		}
		
		$dom->save("centers.xml"); 
	}
?>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>