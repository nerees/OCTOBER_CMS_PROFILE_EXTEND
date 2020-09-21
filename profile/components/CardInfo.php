<?php namespace Nerijus\Profile\Components;

use Cms\Classes\ComponentBase;
use Auth;
use Flash;
use Exception;
use Nerijus\Profile\Models\Profile;
use Config;
use Log;
class CardInfo extends ComponentBase
{
    /**
     * @var string
     */
    public $id;
	public $profile;
	public $Remail; //emailas rivilėje bus naudojamas tikrinti su profiliu arba tikrinti ar ne tuščias

    /**
     * @array
     */
    public $n64info;
    public $test;
	public $testuojamas;
	public $taskai;
	public $yra_info;
	public $terminuota; //pagal mus aktyvi 0 neaktyvi 1 (rodom info ar nerodom veikia kasoje ar neveikia)
	public $blokuota; // blokuota kortele ar neblokuota
	public $now; //data soniniam pasiulymui dienos pagaldiena.htm particle
	

    public function componentDetails()
    {
        return [
            'name'        => 'CardInfo Component',
            'description' => 'Pull information prom profile plugin ...'
        ];
    }

    public function defineProperties()
    {
        return [];
    }

    public function init()
    {
        //This will execute when the component is firsts initialized, including AJAX events.
        
    }

    public function onRun()
    {
        //This code will not execute for AJAX events
        //{{cartinfo.id}} (strict) no conflikt
        //$this->id = "5544444";
        // {% set name = cardinfo.id %}
        $user = Auth::getUser();
        $this->profile = Profile::getFromUser($user);
		// cia sita reikia patikrinima daryti ar yra tokia kortele per api ir jei yra tada ok priskirti prie saskaita, jei ne tada prasyti atnaujinti korteles numeriuka
		//$this->profile->saskaita = $this->generateID(); // gal neturetu to reiket kaskart, reiktu saugotis t1 saskaita is registracijos ten ar papildymo
		//$this->profile->save();
		// end tas ilgas auksciau
        $this->id = $this->generateID();
		$this->n64info = $this->getN64Info($this->id);
		$this->getUserCardInfo();
		$this->now = date('l');
    }

    public function userProfile()
    {
        if (!Auth::check()) {
            return null;
        }
        $user = Auth::getUser();
        return Profile::getFromUser($user);
    }

    /**
     * Update the saskaita iš formos duomenys
     */
    public function onAddCard()
    {
        if (!$profile = $this->userProfile()) {
            return;
        }

        $data = post();
        $profile->fill($data);
        if ($profile->save()){
			Flash::success('Duomenys sėkmingai atnaujinti!');
		}else{
			Flash::error('KLAIDA: išsaugoti nepavyko.');
		}
		$user = Auth::getUser();
		$user->name = $data['saskaita'];
		Flash::error('KLAIDA: išsaugoti nepavyko.' . $data['saskaita'] . ' ');
		$user->save();
        /*
         * Redirect
         */
        //jei reiktu nukreipimo
       // if ($redirect = $this->makeRedirection()) {
       //     return $redirect;
       // }

       // $this->prepareVars();
    }


    /*Pagal userio id gauti duomenis iš API*/
    public function getUserCardInfo()
    {
        if (!$profile = $this->userProfile()) {
            return;
        }

        //$n64_korteles_id = $profile->saskaita;
		if (!empty($this->id)){
			$taskai = $this->getTaskai($this->id);
			if (!empty($taskai->I64[0]->I64_TASKAI)){
				$tsk = (float)$taskai->I64[0]->I64_TASKAI / 1000;
				$tsk = number_format((float)round($tsk, 2, PHP_ROUND_HALF_DOWN),2,'.',',');
			}else{
				$tsk = number_format((float)round(0, 2, PHP_ROUND_HALF_DOWN),2,'.',',');;
			}
			$this->taskai = $tsk;
			$this->yra_info = $this->checkRequiredInfo($this->n64info);
			$this->terminuota = trim($this->n64info->N64[0]->N64_POZ_DATE);
			$this->blokuota = trim($this->n64info->N64[0]->N64_BLOK_POZ);
			$this->page['yra_info'] = $this->yra_info;
			$this->page['gimimo'] = date('Y-m-d', strtotime($this->n64info->N64[0]->N64_GIM_DATA));
			if ($this->page['gimimo'] < "1901-01-01"){
				$this->page['liko_iki_gimtadienio'] = "";
			}else{
				$this->page['liko_iki_gimtadienio'] = $this->countdays(strval($this->page['gimimo'])); //skaiciuojam laika iki gimtadienio
			}
			$this->page['adresas'] = strlen(trim($this->n64info->N64[0]->N64_KODAS_VS)); //tik patikrinimui ar netuscias
			$this->Remail = trim($this->n64info->N64[0]->N64_E_MAIL); //patikrinimui 
			$this->page['mob'] = strlen(trim($this->n64info->N64[0]->N64_MOB_TEL)); //tik patikrinimui ar netuscias
			$this->page['lytis'] = (int)trim($this->n64info->N64[0]->N64_LYTIS);
			$this->page['vardas'] = trim($this->n64info->N64[0]->N64_VARDAS);
			$this->page['adresas1'] = trim($this->n64info->N64[0]->N64_ADR2);
			$this->page['adresas2'] = trim($this->n64info->N64[0]->N64_ADR3);
			if ((trim($this->n64info->N64[0]->N64_KODAS_VS)) == "0"){
				$this->page['miestas'] = "Nenurodytas";
			}else{
				$this->page['miestas'] = $this->getRajonas(trim($this->n64info->N64[0]->N64_KODAS_VS));
			}
			$this->page['telefonas'] = trim($this->n64info->N64[0]->N64_MOB_TEL);
			$this->page['nariai'] = trim($this->n64info->N64[0]->N64_ASM_KODAS);
			$this->page['sms'] = trim($this->n64info->N64[0]->N64_KODAS_LS_1);
			$this->page['naujienlaiskis'] = trim($this->n64info->N64[0]->N64_KODAS_LS_2);
			$this->page['soc'] = trim($this->n64info->N64[0]->N64_KODAS_LS_3);
			
		}else{
			Flash::error('ligu ner this->ID');
			return;
		}
        
    }
	
	// gražina rajoną pagal rajono kodą
	public function getRajonas($id)
	{
		$rajonai = array (
			array(
				"id" => "0",
				"raj" => "-- Pasirinkti --"
			),
			array(
				"id" => "32",
				"raj" => "Akmenės r. sav."
			),
			array(
				"id" => "11",
				"raj" => "Alytaus m. sav."
			),
			array(
				"id" => "33",
				"raj" => "Alytaus r. sav."
			),
			array(
				"id" => "34",
				"raj" => "Anykščių r. sav."
			),
			array(
				"id" => "12",
				"raj" => "Birštono sav."
			),
			array(
				"id" => "36",
				"raj" => "Biržų r. sav."
			),
			array(
				"id" => "15",
				"raj" => "Druskininkų sav."
			),
			array(
				"id" => "42",
				"raj" => "Elektrėnų sav."
			),
			array(
				"id" => "45",
				"raj" => "Ignalinos r. sav."
			),
			array(
				"id" => "46",
				"raj" => "Jonavos r. sav."
			),
			array(
				"id" => "47",
				"raj" => "Joniškio r. sav."
			),
			array(
				"id" => "94",
				"raj" => "Jurbarko r. sav."
			),
			array(
				"id" => "49",
				"raj" => "Kaišiadorių r. sav."
			),
			array(
				"id" => "48",
				"raj" => "Kalvarijos sav."
			),
			array(
				"id" => "19",
				"raj" => "Kauno m. sav."
			),
			array(
				"id" => "52",
				"raj" => "Kauno r. sav."
			),
			array(
				"id" => "58",
				"raj" => "Kazlų Rūdos sav."
			),
			array(
				"id" => "53",
				"raj" => "Kėdainių r. sav."
			),
			array(
				"id" => "54",
				"raj" => "Kelmės r. sav."
			),
			array(
				"id" => "21",
				"raj" => "Klaipėdos m. sav."
			),
			array(
				"id" => "55",
				"raj" => "Klaipėdos r. sav."
			),
			array(
				"id" => "56",
				"raj" => "Kretingos r. sav"
			),
			array(
				"id" => "57",
				"raj" => "Kupiškio r. sav."
			),
			array(
				"id" => "59",
				"raj" => "Lazdijų r. sav."
			),
			array(
				"id" => "18",
				"raj" => "Marijampolės sav."
			),
			array(
				"id" => "61",
				"raj" => "Mažeikių r. sav."
			),
			array(
				"id" => "62",
				"raj" => "Molėtų r. sav."
			),
			array(
				"id" => "23",
				"raj" => "Neringos sav."
			),
			array(
				"id" => "63",
				"raj" => "Pagėgių sav."
			),
			array(
				"id" => "65",
				"raj" => "Pakruojo r. sav."
			),
			array(
				"id" => "25",
				"raj" => "Palangos m. sav."
			),
			array(
				"id" => "27",
				"raj" => "Panevėžio m. sav."
			),
			array(
				"id" => "66",
				"raj" => "Panevėžio r. sav."
			),
			array(
				"id" => "67",
				"raj" => "Pasvalio r. sav."
			),
			array(
				"id" => "68",
				"raj" => "Plungės r. sav."
			),
			array(
				"id" => "69",
				"raj" => "Prienų r. sav."
			),
			array(
				"id" => "71",
				"raj" => "Radviliškio r. sav."
			),
			array(
				"id" => "72",
				"raj" => "Raseinių r. sav."
			),
			array(
				"id" => "74",
				"raj" => "Rietavo sav."
			),
			array(
				"id" => "73",
				"raj" => "Rokiškio r. sav."
			),
			array(
				"id" => "75",
				"raj" => "Skuodo r. sav."
			),
			array(
				"id" => "84",
				"raj" => "Šakių r. sav."
			),
			array(
				"id" => "85",
				"raj" => "Šalčininkų r. sav."
			),
			array(
				"id" => "29",
				"raj" => "Šiaulių m. sav."
			),
			array(
				"id" => "91",
				"raj" => "Šiaulių r. sav."
			),
			array(
				"id" => "87",
				"raj" => "Šilalės r. sav."
			),
			array(
				"id" => "88",
				"raj" => "Šilutės r. sav."
			),
			array(
				"id" => "89",
				"raj" => "Širvintų r. sav."
			),
			array(
				"id" => "86",
				"raj" => "Švenčionių r. sav."
			),
			array(
				"id" => "77",
				"raj" => "Tauragės r. sav."
			),
			array(
				"id" => "78",
				"raj" => "Telšių r. sav."
			),
			array(
				"id" => "79",
				"raj" => "Trakų r. sav."
			),
			array(
				"id" => "81",
				"raj" => "Ukmergės r. sav."
			),
			array(
				"id" => "82",
				"raj" => "Utenos r. sav."
			),
			array(
				"id" => "38",
				"raj" => "Varėnos r. sav."
			),
			array(
				"id" => "39",
				"raj" => "Vilkaviškio r. sav."
			),
			array(
				"id" => "13",
				"raj" => "Vilniaus m. sav."
			),
			array(
				"id" => "41",
				"raj" => "Vilniaus r. sav."
			),
			array(
				"id" => "30",
				"raj" => "Visagino sav."
			),
			array(
				"id" => "43",
				"raj" => "Zarasų r. sav."
			)
		);

		$rajonaiJSON = json_encode($rajonai);
		$this->page['rajonai'] = $rajonaiJSON;
		//$this->page['rajonai'] = $rajonai;
		$raj = json_decode($rajonaiJSON, true);
		
		//generuojam selecto html kelti i atskirą funkciją
		$htm = "";
		foreach ($raj as $select_element){
			$htm .= "<option value=" . $select_element['id'] . ">" . $select_element['raj'] ."</option>"; 	
		}
		$this->page['htm'] = $htm;
		//end generuojam selecto html	
		
		foreach ($raj as $item){
				
				if (strval($item['id']) == $id){
					return $item['raj'];
				}
		}

		return 0;			
	}
	
	
	// tikrinam ar visi reikalingi profilio laukai užpildyti (true / false) 
	public function checkRequiredInfo($kortele)
	{
		
		if (!empty($kortele)){
		
			if (empty(trim($kortele->N64[0]->N64_GIM_DATA))){
				return false;
			}
			if (empty(trim($kortele->N64[0]->N64_KODAS_VS))){
				return false;
			}
			//if (empty(trim($kortele->N64[0]->N64_E_MAIL))){
			//	return false; //cia gal reiktu pristirti accounto emaila automatiskai jei tuscias
			//}
			if (empty(trim($kortele->N64[0]->N64_MOB_TEL))){
				return false;
			}
			
			return true;
		
		}
		else {
			Flash::error('Nepavyko gauti kortelės duomenų');
			return;
		}
	}
	
	// generuojam id iš kortelės numerio = n64_kodas_dl
	public function generateID()
	{
		$user = Auth::getUser();
		$this->testuojamas = substr($user->name, -11, -1);
		return substr($user->name, -11, -1);
	}

    //kreipiames i api visiems duomenims iš N64
    public function getN64Info($id)
    {
		$key = Config::get('nerijus.profile::key', null);
		$curl = curl_init();
        
        curl_setopt_array($curl, array(
          CURLOPT_URL => "https://api.manorivile.lt/client/v2",
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS =>"{\n    \"method\": \"GET_N64_LIST\",\n    \"params\": {\n        \"fil\": \"n64_kodas_dl='".$id."'\"\n    }\n}\n",
          CURLOPT_HTTPHEADER => array(
            "ApiKey: ".$key."",
            "Content-Type: application/json"
          ),
        ));
        
        $response = curl_exec($curl);
        curl_close($curl);
        $xml = simplexml_load_string($response);
        //$xml = "lalaila";
        return $xml; 

    }
	
	
	//kreipiames i api tašku sumai gauti
    public function getTaskai($id)
    {
		$key = Config::get('nerijus.profile::key', null);
		$curl = curl_init();
        
        curl_setopt_array($curl, array(
          CURLOPT_URL => "https://api.manorivile.lt/client/v2",
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS =>"{\n    \"method\": \"GET_I64_LIST\",\n    \"params\": {\n        \"fil\": \"i64_kodas_dl='".$id."'\"\n    }\n}\n",
          CURLOPT_HTTPHEADER => array(
            "ApiKey: ".$key."",
            "Content-Type: application/json"
          ),
        ));
        
        $response = curl_exec($curl);
        curl_close($curl);
        $xml = simplexml_load_string($response);
        //$xml = "lalaila";
        return $xml; 

    }
    
	public function updateProfileAPI($uzklausa)
    {
		$key = Config::get('nerijus.profile::key', null);
		$curl = curl_init();
        
        curl_setopt_array($curl, array(
			CURLOPT_URL => "https://api.manorivile.lt/client/v2",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => $uzklausa,
			CURLOPT_HTTPHEADER => array(
				"Content-Type: application/xml",
				"ApiKey: ".$key.""
			),
		));
        
        $response = curl_exec($curl);
        curl_close($curl);
        //$xml = simplexml_load_string($response);
        //$xml = "lalaila";
        //return $xml; 
		//echo $response;
		return true;
    }
	
	
	/**
     * Ar veikia API
     */
    public function worksAPI()
    {
		/*if (isset($this->id)){
			return true; //tikrinimas ar yra id, jei yra tai gaunamas atsakymas is api ir prielaida jog api veikia
		}else{
			return false;
		}*/
		return true;
    }
	
	/* Atblokuoja klientas kortelę N64_BLOK_POZ = 0 */
	
	public function onAtblokuoti()
	{
		$id = $this->generateID();
		//$data = post();
		$uzklausa = "<body>\r\n    <method>EDIT_N64</method>\r\n    <params>\r\n        <oper>U</oper>\r\n    </params>\r\n    <data>\r\n        <N64>\r\n             <N64_KODAS_DL>".$id."</N64_KODAS_DL>\r\n             <N64_BLOK_POZ>0</N64_BLOK_POZ>\r\n             </N64>\r\n    </data>\r\n</body>";
		
		if ($this->updateProfileAPI($uzklausa)){
				//Log::info('uzklausa: '.$uzklausa);
				//Flash::success('Kortelė atblokuota sėkmingai!');
				//sleep(3);
				return redirect()->refresh();
			}
			else{
				Flash::error('Profilio informacijos atnaujinti nepavyko :(');
			}
	}
	
	/* Blokuoja klientas kortelę N64_BLOK_POZ = 1*/
	
	public function onBlokuoti()
	{
		$id = $this->generateID();
		//$data = post();
		$uzklausa = "<body>\r\n    <method>EDIT_N64</method>\r\n    <params>\r\n        <oper>U</oper>\r\n    </params>\r\n    <data>\r\n        <N64>\r\n             <N64_KODAS_DL>".$id."</N64_KODAS_DL>\r\n             <N64_BLOK_POZ>1</N64_BLOK_POZ>\r\n             </N64>\r\n    </data>\r\n</body>";
		
		if ($this->updateProfileAPI($uzklausa)){
				//Log::info('uzklausa: '.$uzklausa);
				//Flash::success('Kortelė blokuota sėkmingai!');
				//sleep(3);
				return redirect()->refresh();
			}
			else{
				Flash::error('Profilio informacijos atnaujinti nepavyko :(');
			}
	}
	
        /**
     * UPDATE the user INFO on API
     */
    public function onUpdateProfile()
    {
        //try {
            //if (!$this->worksAPI()) {
            //    throw new ApplicationException('Kilo problemų kreipiantis į lojalumos sistemos serverį');
           // }

            /*
             * form input
             */
			$user = Auth::getUser();
			$id = $this->generateID(); 
			$data = post();
			//Log::info('koks id: '.$id);
						
			$uzklausa = "<body>\r\n    <method>EDIT_N64</method>\r\n    <params>\r\n        <oper>U</oper>\r\n    </params>\r\n    <data>\r\n        <N64>\r\n             <N64_KODAS_DL>".$id."</N64_KODAS_DL>\r\n             <N64_VARDAS>".$data['vardas']."</N64_VARDAS>\r\n             <N64_GIM_DATA>".$data['gimimo']."</N64_GIM_DATA>\r\n             "; 
            
			if ($data['vietove'] != "0"){
				$uzklausa .="<N64_KODAS_VS>".$data['vietove']."</N64_KODAS_VS>\r\n             "; 
			}
			
			if ((!empty($data['sms'])) and ($data['sms'] == "1")){
				$uzklausa .="<N64_KODAS_LS_1>1</N64_KODAS_LS_1>\r\n             "; 
			}else{
				$uzklausa .="<N64_KODAS_LS_1>0</N64_KODAS_LS_1>\r\n             "; 
			}
			
			if ((!empty($data['naujienlaiskis'])) and ($data['naujienlaiskis'] == "1")){
				$uzklausa .="<N64_KODAS_LS_2>1</N64_KODAS_LS_2>\r\n             "; 
			}else{
				$uzklausa .="<N64_KODAS_LS_2>0</N64_KODAS_LS_2>\r\n             ";
			}
			
			if ((!empty($data['soc'])) and ($data['soc'] == "1")){
				$uzklausa .="<N64_KODAS_LS_3>1</N64_KODAS_LS_3>\r\n             "; 
			}else{
				$uzklausa .="<N64_KODAS_LS_3>0</N64_KODAS_LS_3>\r\n             "; 
			}

			//jei tuščias emailas tai imam vartotojo accountą (kažin ar nereikėtų čia pet kokiu atveju dėti registracijos acc?? anketoje gali būti netikras nepatvirtintas)
			if ( (strlen($data['remail']) < 5) || (strstr($data['remail'], '@') == false) || (strstr($data['remail'], '.') == false) ){
				$uzklausa .="<N64_E_MAIL>".$user->email."</N64_E_MAIL>\r\n             ";
			}

			$uzklausa .= "<N64_LYTIS>".$data['lytis']."</N64_LYTIS>\r\n             <N64_ASM_KODAS>".$data['nariai']."</N64_ASM_KODAS>\r\n             <N64_MOB_TEL>".$data['tel']."</N64_MOB_TEL>\r\n             <N64_ADR2>".$data['adresas1']."</N64_ADR2>\r\n             <N64_ADR3>".$data['adresas2']."</N64_ADR3>\r\n             </N64>\r\n    </data>\r\n</body>";
			
			//Log::info('uzklausa: '.$uzklausa);
			
			if ($this->updateProfileAPI($uzklausa)){
				//Log::info('uzklausa: '.$uzklausa);
				Flash::success('Profilio informacija sėkmingai atnaujinta!');
				//sleep(3);
				//return redirect()->refresh();
			}
			else{
				Flash::error('Profilio informacijos atnaujinti nepavyko :(');
			}
       // }
        //catch (Exception $ex) {
            /*if (Request::ajax()) throw $ex;
            else*/// Flash::error($ex->getMessage());
       // }
    }    
	
	//skaiciuojam laika iki gimtadienio
	public function countdays($date)   // declare the function and get the birth date as a parameter
	{
		 $olddate =  substr($date, 4); // use this line if you have a date in the format YYYY-mm-dd.
		 $newdate = date("Y") ."".$olddate; //set the full birth date this year
		 $nextyear = date("Y")+1 ."".$olddate; //set the full birth date next year
		 
		 
			if(strtotime($newdate) > strtotime(date("Y-m-d"))) //check if the birthday has passed this year. In order to check use strotime(). if it has not....
			{
			$start_ts = strtotime($newdate); // set a variable equal to the birthday in seconds (Unix timestamp, check php manual for more information)
			$end_ts = strtotime(date("Y-m-d"));// and a variable equal to today in seconds
			$diff = $end_ts - $start_ts; // calculate the difference of today minus birthday
			$n = round($diff / (60*60*24));// divide the diffence with the seconds of one day to get the dates. Use round() to get a round number.
										//(60*60*24) represents 60 seconds * 60 minutes * 24 hours = 1 day in seconds. You can also directly write 86400
			$return = substr($n, 1); //you need this to get the right value without -
			return $return; // return the value
			}
			else // else if the birthday has past this year
			{
			$start_ts = strtotime(date("Y-m-d")); // set a variable equal to the today in seconds
			$end_ts = strtotime($nextyear); // and a variable with the birtday next year
			$diff = $end_ts - $start_ts; // calculate the difference of next birthday minus today
			$n = round($diff / (60*60*24)); // divide the diffence with the seconds of one day to get the dates.
			$return = $n; // assign the dates to return
			return $return; // return the value
		
			}
		
		}
}
