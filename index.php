global $USER;
$filter = ["ID" => implode('|', array_keys($arUserProvider)), '!UF_TYPE_OF_SALE' => false];
$rsUsers = CUser::GetList(($by = "NAME"), ($order = "desc"), $filter, ['FIELDS' => ['ID'], 'SELECT' => ['UF_TYPE_OF_SALE']]);
while ($arUser = $rsUsers->Fetch()) {
    $arProviders[$arUserProvider[$arUser['ID']]]['UF_TYPE_OF_SALE'] = $arUser['UF_TYPE_OF_SALE'];
}

$arHLBlock2 = Bitrix\Highloadblock\HighloadBlockTable::getById(60)->fetch();
$obEntity2 = Bitrix\Highloadblock\HighloadBlockTable::compileEntity($arHLBlock2);
$strEntityDataClass2 = $obEntity2->getDataClass();

$rsData = $strEntityDataClass2::getList(array(
    'select' => array('*'),
    'order' => ['ID' => 'ASC'],
    'filter' => [
        'UF_ID_PRODUCT' => '', 
        '>UF_DATE_POST' => '08.08.2019 00:00:00', 
        'UF_COMMENT' => '',
        '!UF_IMAGE' => ''
    ],
    // 'limit' => 1
 ));
$i = 0;
$z = 0;
$el = new CIBlockElement;
while($arItem = $rsData->fetch()) { 
    
    // continue;
   

    $userPavilionXml = $arProviders[$arItem['UF_PROVIDER']]['UF_XML_ID'];
    $images = explode(';', $arItem['UF_IMAGE']);
    $image = array_shift($images);

    $str = mb_strtolower($arItem['UF_TEXT']);


    preg_match_all('/цена\D{1,10}(\d{1,5}\s{0,2}\d{1,3})[\s\.р]/mi', $str, $matches, PREG_SET_ORDER, 0);

    // Print the entire match result
    $price = onlyNum($matches[0][0]);

    // echo '<br>';
    if(!$matches){
        preg_match_all('/(\d{2,5})\s{0,2}(р\w?|разм|руб|p)/mi', $str, $matches, PREG_SET_ORDER, 0);
        // echo '<pre>';
        // print_r($matches);
        // echo '</pre>';
        foreach ($matches as $key => $value) {
            if($value[1]){
                $price =  $value[1];
                $matches = true;
                break;
            }else{
                $matches = [];
            }
        }
    }

    if(!$matches){
        preg_match_all('/цена.{1,2}(\d{2,4})/mi', $str, $matches, PREG_SET_ORDER, 0);
         $price = onlyNum($matches[0][0]);
        // echo '[3]'. $price;
        // echo '<br>';
        // print_r($matches);
    }
    if(!$matches){
        preg_match_all('/\s(\d{3,4})(?!см)/mi', $str, $matches, PREG_SET_ORDER, 0);
         $price = onlyNum($matches[0][0]);
        // echo '[4]'. $price;
        // echo '<br>';
        // print_r($matches);
    }
    $textNew = '';
    $arItem['UF_TEXT'] = strip_tags($arItem['UF_TEXT']);
    if($price){
        $textNew = str_replace($price, '<div class="parser_price">'.$price.'</div>', $arItem['UF_TEXT']);
    }else{
        $textNew = $arItem['UF_TEXT'];
    }

    // if(!$price){
    //     $price = 9999;
    // }


    // preg_match_all('/размер\D{1,10}((\d{1,5}\D{1,2})*)/m', $str, $matches, PREG_SET_ORDER, 0);

    // if ($USER->IsAdmin()){
    //     echo "<pre style='text-align:left'>";
    //     print_r($price);
    //     echo "</pre>";
    // }
    // $sizes = [];
    // $sizes = explode(' ', trim(notNumReplace($matches[0][0])));
    // print_r($sizes);

    $str = str_replace('размер в размер', 'размер', $str);
            // $str = str_replace('размеры размеры', 'размер', $str);

    $matches = [];
    // $str = $row['UF_TEXT'];
    // echo  $str;
    // preg_match_all('/размер\D{0,10}((\d{1,5}\D{0,3})*)/', $str, $matches, PREG_SET_ORDER, 0);
    $matches = getSizeRazmer($str);

    //Проверка на опт и упаковку
    $upakNum = '';
    if(opt($str) && numUpak($str) > 1 && numUpak($str) < 20){
        // echo "ОПТ";
        //поставить 
        $upakNum = numUpak($str);

    }


    $priceTemp = ''; 
    foreach ($matches as $key => $value) {
        if(mb_strlen($value[2]) > mb_strlen( $priceTemp)){
             $priceTemp = $value[2];
             
        }
    }
    $textNew = str_replace($priceTemp, '<div class="parser_size">'.$priceTemp.'</div>', $textNew);
    // print_r($priceTemp);

    $size = notNumReplace( $priceTemp);
    $sizes = explode(' ', trim($size));
    // echo '[1] '.$size;
    // echo '<pre>';
    // print_r($matches);
    // echo '</pre>';
    $arSizeNew = addSizeRange($size);


    if($arSizeNew){
        $sizes = $arSizeNew;
    }

    //если размер 1 то ищем размеры по другому
    // if(count($sizes) < 2){
    // 	$sizes = searchSize($arItem['UF_TEXT']);
    // }

    // checkSize($sizes);



    //проверка на вхожнение Единий и похожих
    if(checkOneSize($str)){
        unset($sizes);
    }

    // если есть дубли размеров, то это упаковка
    if(dubleSize($sizes)){
    	$upakNum = true;
    }


    
    $PROP = [
        // 'CVET' => $_REQUEST['color_prod'],
        // 'PROIZVODITEL' => $_REQUEST['country_prod'],
        // 'PROVIDERS_SIZE' => utfToWin($sizes),
        // 'IB_PROVIDER' => $userPavilionXml,
        'PROVIDERS_SADOVOD' => $userPavilionXml,
        'DATE_PARSING' => date('d.m.Y H:i:s')
    ];
    $PROP['COLORS'] = [];
    if($images){
        foreach ($images as $key => $value) {
            $PROP['COLORS'][] = CFile::MakeFileArray($value);
        }
        
    }
    if($sizes){
        $PROP['PROVIDERS_SIZE'] = utfToWin($sizes);
        if(count($PROP['PROVIDERS_SIZE']) == 1){
            $PROP['PROD_SIZE'] = utfToWin($sizes[0]);
            unset($PROP['PROVIDERS_SIZE']);
        }
    }
    if($upakNum){
        $PROP['PACK_NUM'] = $upakNum;
        $price = $price * $upakNum;
    }
