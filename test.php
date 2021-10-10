<?php


//$snipeHost = "172.20.10.3";
$snipeHost = "192.168.0.28";
$apikey = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIxIiwianRpIjoiNWM2YzNlODAwYjk4MzM2NmVjODU5ZjFhNGY2NjI5MzAxOTc1ZjkyOTU5Mjc3NTQzNDIzOTNlZDBlZTUyMGRkMWY0Y2MzYzdkMjQ2ZTE3YWUiLCJpYXQiOjE2MzMzNjI2MDcsIm5iZiI6MTYzMzM2MjYwNywiZXhwIjoxNjY0ODk4NjA3LCJzdWIiOiIxIiwic2NvcGVzIjpbXX0.W5CgTLLdrDPAsLYGsCqUfUa5fuGIJWxo6qDHmRctEkQgiXiI5p1yE25_gB0MVoL1CTrno0fPUNQ5kGXW95WgWv5_kmp9-TuceZrHWmub-Tak3DJYWb6stMGIxHbbYQvuPvu3kwPhFgElZaMfCGK0DBc6IdgwQMTqBkDP55f9kw9NUJkR6lUbas5ny21PfDVSQSi4fqCnU0kb1fpJEysFWqDxdO6bPnvoVC1waOMp5Ml2N7FszQ3L_XjpEjVSWa5Dko6uQCplmHZ_0Llia24D10_eURtZlyBJfXEllgKZihshllNkeZVnC8uQj5c8ov3L8EatPRWPrFR5N33K3Fz1wCGIw4KF4D74zIEWOmGn5nxvcXFo4X2lDiexwaj02bfnmJwTp49zPdUVApOMODqiW8U7A4tf4IXRQyy6OGE5NIl3LrhREK2DP5lhczte57oIszyexmMoSkK5aB8XWIdkS6nKhr7ismhJmTiQrhU5LpIisZnLhqUKXI9F5dKZdsMEgaGlIQ0Q7bd0-D9KXLKAZWXUPJYfSOhswD9Szj_ViKD9cxwC6UtJ6Ynqcceyz31U8LM2BNOYGd8S1Xz2Cpw1LLhIm7H4HWkdQe4QGUvyUMdogY31zwSgltLbcOmTc1Q0Sxa_Ro6WxuU4_AizK1Afn2pE5TmU_Hcxj2on-ihaOlk";


if (isset($_GET['assetID'])) {

    $apiFields['start_date'] = date("Y-m-d");
    $assetTag = $_GET['assetID'];
    $response = curlStuff("/api/v1/hardware/bytag/" . $assetTag, "GET", $apiFields);
    // error handling here - no such asset - ?
    $currentAsset = json_decode($response);
    if (isset($currentAsset->status)) {
        if ($currentAsset->status === "error") {
            echo ("$currentAsset->messages");
            die;
        }
    }

    $currentAsset = json_decode($response);
    if (isset($currentAsset->asset_tag)) {
        echo ("<div id=\"loadedAsset\" hidden>" . $currentAsset->asset_tag . "</div><h2>" . $currentAsset->model->name . "</h2>"); // need this to keep reference current asset
    }
    // here
    ////////////////////////////////////////
    // get an asset
    $response = curlStuff("/api/v1/hardware/bytag/" . $currentAsset->asset_tag, "GET", null);
    $assetObj = json_decode($response);

    // build a form from the custom fields. fill with last values from asset 
    $allCustomFields = json_decode(curlStuff("/api/v1/fields", "GET", null)); // get all custom definitions
    echo ("<form id=\"resultsForm\">");
    foreach ($assetObj->custom_fields as $fieldName => $currField) {
        if (strpos($fieldName, "_test_") !== False) { // custom fields we want to use are prepended with _test_
            echo ("<div class=\"form-group row\">");
            // set currFieldDefinition to the appropriate custom Definition
            foreach ($allCustomFields->rows as $index => $customFieldDefinition) {
                if ($customFieldDefinition->db_column_name == $currField->field) {
                    $currFieldDefinition = $customFieldDefinition;
                }
            }
            // set form elements. only text and listbox currently implemented
            echo ("<label for=\"" . $currFieldDefinition->db_column_name . "\"  class=\"col-sm-2 col-form-label\">" . str_replace("_test_", "", $currFieldDefinition->name));
            echo ("</label><div class=\"col-sm-10\">");
            if (($currFieldDefinition->type == "text") && ($currFieldDefinition->format == "NUMERIC")) {
                echo ("<input type=\"number\" name=\"" . $currFieldDefinition->db_column_name . "\" value=\"" . $currField->value . "\" required>");
            } else if ($currFieldDefinition->type == "text") {
                echo ("<input type=\"text\" name=\"" . $currFieldDefinition->db_column_name . "\" value=\"" . $currField->value . "\" required>");
            }
            if ($currFieldDefinition->type == "listbox") {
                echo ("<select name=\"" . $currFieldDefinition->db_column_name . "\">");
                foreach ($currFieldDefinition->field_values_array as $option) {
                    echo ("<option value=\"" . $option . "\">" . $option . "</option>");
                }
                echo ("</select>");
            }
            echo ("</div></div>");
        }
    }

    // we need supplier id
    $suppliers = json_decode(curlStuff("/api/v1/suppliers", "GET", null));
    echo ("<div class=\"form-group row\">");
    echo ("<label for=\"supplier\" class=\"col-sm-2 col-form-label\">Tested by</label><div class=\"col-sm-10\"><select name=\"supplier\">");
    foreach ($suppliers->rows as $supplierID => $supplier) {
        echo ("<option value=\"" . $supplierID . "\">" . $supplier->name . "</option>");
    }
    echo ("</select></div></div></form>");
    echo <<< END
    <div class="container">
        <div class="row">
            <div class="col-sm">
            <button id="fail" onclick="failTest()" class="btn btn-danger btn-block">Fail</button>

            </div>
            <div class="col-sm">
            <button id="pass" onclick="passTest()" class="btn btn-success btn-block">Pass</button>

            </div>
        </div>
    </div>
    END;
    // show all historic maintenances 
    $apiFields = array();
    $maintenances = (curlStuff("/api/v1/maintenances?asset_id=" . $currentAsset->id, "GET", null));
    // var_dump($maintenances);
    $maintenances = json_decode($maintenances);
    echo ("<h3>Previous Maintenances</h3>");
    echo ("<table class=\"table\"> <th>Asset</th><th>Model Name</th><th>Maintenance Type</th><th>Notes</th><th>Date</th>");
    foreach ($maintenances->rows as $row) {
        echo ("<tr><td>");
        echo $row->asset->asset_tag;
        echo ("</td><td>");
        echo $row->model->name;
        echo ("</td><td>");
        echo $row->asset_maintenance_type;
        echo ("</td><td>");
        echo $row->notes;
        echo ("</td><td>");
        echo $row->start_date->date;
        echo ("</td>");
        echo ("</tr>");
    }
    echo ("</table><br><br>");
}



if (isset($_POST['testPassed'])) {
    $maintNotes = "FAIL ";
    if ($_POST['testPassed'] == "true") {
        $maintNotes = "PASS ";
        // if we passed, update the audit date for the asset
        /// 
        // try and pat pass 
        $auditFields = array(); // clear the options
        $auditFields['asset_tag'] = $_POST['assetID'];

        foreach ($_POST as $key => $value) {
            if (strpos($key, "test_period") !== false) { // if test_period field exists, use it to update next audit date
                $newDate = new DateTime();
                $interval = 12;
                if ($value >= 0) $interval = $value;
                $newDate->add(new DateInterval("P" . $interval . "M"));
                $auditFields['next_audit_date'] = $newDate->format('Y-m-d');
            }
        }
        $response = curlStuff("/api/v1/hardware/audit", "POST", $auditFields); // update audit values
        $response = json_decode($response);
        echo ($response->messages);
    }
    // irrespective of pass or failure, log the result in both a maintenace record and on the item itself.
    // create a maintenance with the fields saved as a note.
    // get the assetID from the tag.
    $allCustomFields = json_decode(curlStuff("/api/v1/fields", "GET", null)); // get all custom definitions

    $assetObj = json_decode(curlStuff("/api/v1/hardware/bytag/" . $_POST['assetID'], "GET", null));
    $today = new DateTime();
    $apiFields = array(); // clear request the options
    $apiFields['title'] = "Test";
    $apiFields['asset_id'] = $assetObj->id;
    $apiFields['supplier_id'] = $_POST['supplier'];
    $apiFields['start_date'] = $today->format('Y-m-d');
    $apiFields['completion_date'] = $today->format('Y-m-d');
    $apiFields['asset_maintenance_type'] = "Test";
    foreach ($_POST as $key => $value) {
        if (strpos($key, "_test_") !== False) { // custom fields we want to use are prepended with _test_
            foreach ($allCustomFields->rows as $fieldName => $currFieldDefinition) {
                if ($currFieldDefinition->db_column_name == $key) { // convert back from db_names to human names
                    $maintNotes = $maintNotes . $currFieldDefinition->name . ":" . $value . " ";
                }
            }
        }
    }
    $maintNotes = str_replace("_test_", "", $maintNotes); // 
    $apiFields['notes'] = $maintNotes;
    $response = json_decode(curlStuff("/api/v1/maintenances", "POST", $apiFields));
    var_dump($response);
    echo ($response->messages);
}



function curlStuff($endpoint, $method, $postFields)
{
    global $snipeHost, $apikey;
    $opt =
        [
            CURLOPT_URL => $snipeHost . $endpoint,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => [
                "Accept: application/json",
                'Content-Type' => 'application/json',
                "Authorization: Bearer " . $apikey
            ]
        ];
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

    curl_setopt_array(
        $curl,
        $opt
    );

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
    } else {
        return $response;
    }
}
