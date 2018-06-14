<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\File;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //$this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */

    public function index()
    {
        return view('upload');
    }

    public function data(Request $request)
    {

        /* Parse PDF to Text */
        $parser = new \Smalot\PdfParser\Parser();

        if(!is_null($request->file('pdfFile'))){
            $pdf    = $parser->parseFile($request->file('pdfFile'));
            $text   = $pdf->getText();
            if(strpos($text, 'Naam') !== false){
                return $this->pdfNL($request);
            }elseif (strpos($text, 'Nom') !== false){
                return $this->pdfFR($request);
            }
        }else{
            dd('No file uploaded.');
        }

    }

    public function pdfNL($request)
    {
        /* Parse PDF to Text */
        $parser = new \Smalot\PdfParser\Parser();
        $pdf    = $parser->parseFile($request->file('pdfFile'));
        $text    = $pdf->getText();

        /* Get company number and extract numbers and letters */
        $companyNumber = $this->get_string_between(json_encode($text),'Ondernemingsnr\t','\t');
        $companyNumber = str_replace(' ', '', $companyNumber);
        $companyNumber1 = preg_replace("/[^A-Z]+/", "", $companyNumber);
        $companyNumber2 = preg_replace("/[^0-9]+/", "", $companyNumber);

        /* Call API and check for valid company */
        $vat = $this->viesCheckVAT ($companyNumber1, $companyNumber2);

        /* Get Sales Person */
        $salesPerson    = $this->get_string_between(json_encode($text),'Naam\t:','PROSPECTIE');
        $salesPerson    = trim($salesPerson);
        if(!is_null($salesPerson)){ $test['SalesPerson'] = $salesPerson; }

        /* Get Date Visited */
        $dateVisited    = $this->get_string_between(json_encode($text),'bezoek\t','\tUur');
        $dateVisited    = trim(str_replace('\\', '', $dateVisited));
        if(!is_null($dateVisited)){ $test['DateVisited'] = $dateVisited; }

        /* Get Time Visited */
        $timeVisited    = $this->get_string_between(json_encode($text),'Uur\tbezoek\t','\tBedrijfsnaam');
        if(!is_null($timeVisited)){ $test['TimeVisited'] = $timeVisited; }

        /* Get Company Name */
        if(isset($vat['name'])){
            $test['CompanyName']        = $vat['name'];
        }

        /* Get Company Number */
        if(!is_null($companyNumber)){ $test['CompanyNumber'] = $companyNumber; }

        /* Get Company Address */
        if(isset($vat['address'])){
            $test['CompanyAddress']     = json_decode(str_replace('\n',', ',json_encode($vat['address'])));
        }

        if(!is_null($request->get('download'))){
            $fileName = time() . '_datafile.json';
            \Storage::disk('public')->put('/'.$fileName, json_encode($test));
            return response()->download(storage_path('app/public/'.$fileName), 'document.json');
        }

        return response()->json($test);
    }

    public function pdfFR($request)
    {
        /* Parse PDF to Text */
        $parser = new \Smalot\PdfParser\Parser();
        $pdf    = $parser->parseFile($request->file('pdfFile'));
        $text   = $pdf->getText();

        /* Get company number and extract numbers and letters */
        $companyNumber = $this->get_string_between(json_encode($text),'Nr\tTVA ','\t');
        $companyNumber = str_replace(' ', '', $companyNumber);
        $companyNumber1 = preg_replace("/[^A-Z]+/", "", $companyNumber);
        $companyNumber2 = preg_replace("/[^0-9]+/", "", $companyNumber);

        /* Call API and check for valid company */
        $vat = $this->viesCheckVAT ($companyNumber1, $companyNumber2);

        /* Get Sales Person */
        $salesPerson    = $this->get_string_between(json_encode($text),'Nom\t:',' RAPPORT');
        $salesPerson    = trim($salesPerson);
        if(!is_null($salesPerson)){ $test['SalesPerson'] = $salesPerson; }

        /* Get Date Visited */
        $dateVisited    = $this->get_string_between(json_encode($text),'visite','\tHeure');
        $dateVisited    = trim(str_replace('\\', '', $dateVisited));
        if(!is_null($dateVisited)){ $test['DateVisited'] = $dateVisited; }

        /* Get Time Visited */
        $timeVisited    = $this->get_string_between(json_encode($text),'visite\t','\tNom');
        if(!is_null($timeVisited)){ $test['TimeVisited'] = $timeVisited; }

        /* Get Company Name */
        $test['CompanyName']        = $vat['name'];

        /* Get Company Number */
        if(!is_null($companyNumber)){ $test['CompanyNumber'] = $companyNumber; }

        /* Get Company Address */
        $test['CompanyAddress']     = json_decode(str_replace('\n',', ',json_encode($vat['address'])));

        if(!is_null($request->get('download'))){
            $fileName = time() . '_datafile.json';
            \Storage::disk('public')->put('/'.$fileName, json_encode($test));
            return response()->download(storage_path('app/public/'.$fileName), 'document.json');
        }

        return response()->json($test);
    }

    private function get_string_between($string, $start, $end){
        $string = ' ' . $string;
        $ini = strpos($string, $start);
        if ($ini == 0) return '';
        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;
        return substr($string, $ini, $len);
    }

    private function viesCheckVAT($countryCode, $vatNumber, $timeout = 30) {
        $response = array ();
        $pattern = '/<(%s).*?>([\s\S]*)<\/\1/';
        $keys = array (
            'countryCode',
            'vatNumber',
            'requestDate',
            'valid',
            'name',
            'address'
        );

        $content = "<s11:Envelope xmlns:s11='http://schemas.xmlsoap.org/soap/envelope/'>
  <s11:Body>
    <tns1:checkVat xmlns:tns1='urn:ec.europa.eu:taxud:vies:services:checkVat:types'>
      <tns1:countryCode>%s</tns1:countryCode>
      <tns1:vatNumber>%s</tns1:vatNumber>
    </tns1:checkVat>
  </s11:Body>
</s11:Envelope>";

        $opts = array (
            'http' => array (
                'method' => 'POST',
                'header' => "Content-Type: text/xml; charset=utf-8; SOAPAction: checkVatService",
                'content' => sprintf ($content, $countryCode, $vatNumber),
                'timeout' => $timeout
            )
        );

        $ctx = stream_context_create ($opts);
        $result = file_get_contents ('http://ec.europa.eu/taxation_customs/vies/services/checkVatService', false, $ctx);

        if (preg_match(sprintf($pattern, 'checkVatResponse'), $result, $matches)) {
            foreach ($keys as $key)
                preg_match(sprintf($pattern, $key), $matches [2], $value) && $response [$key] = $value [2];
        }
        return $response;
    }
}
