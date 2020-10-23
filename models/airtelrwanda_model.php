<?php

class Airtelrwanda_Model extends GeneralOperator {

    function __construct() {
        parent::__construct();
    }


    function ProcessDebitCompletedRequest($req_data,$log_name){

            $req_array = $this->map->FormatXMLTOArray($req_data);
            //print_r($req_array);die();
            $this->log->LogRequest($log_name,"AirtelrwandaModel:  ProcessDebitCompletedRequest data ". var_export($req_array,true),2);

      $transaction = $this->VerifyMerchantReference($req_array['transaction_reference_number']);
          if(!empty($transaction)&&$transaction[0]['transaction_status']=='pending'){
                if($req_array['operator_status']==200){
                $req_array['operator_status']='successful';
                }
      $error_codes=$this->MatchOPeratorRespcodes($req_array['operator_status']);
      $combined =array_merge($req_array,$error_codes);
     $this->log->LogRequest($log_name,"AirtelrwandaModel:  ProcessDebitCompletedRequest log merged data". var_export($combined,true),2);
       $this->OperatorHandler($combined,$transaction,$log_name);
                }else{
        $this->log->LogRequest($log_name,"AirtelrwandaModel:  ProcessDebitCompletedRequest exited reference not found ",3);

                   }

	     }



       function ProcessPendingTransactions($operator){


       $transactions=$this->GetOperatorPendingTransactions($operator);
//print_r($transactions);die();

           if(count($transactions)>0){
     		 foreach ($transactions as $key => $value) {

               $transd=strtotime($value['transaction_date']);
               $now=strtotime(date('Y-m-d H:i:s'));
               //$timediff=date_diff($now,$transd );
               $interval  = abs($now - $transd);
              $minutes   = round($interval / 60);
     	     // if($minutes>=1){

            	$this->CompletePendingTransactions($transactions[$key],'operator_'.$transactions[$key]['operator_id'].'_check_status');
            // }


             }


     	  }


       }

     function CompletePendingTransactions($data,$log_name){

            //   print_r($data);die();
      //$this->log->LogToFile($vndr, "AirtelrwandaModel::CompletePendingTransactions data ", 2, 3);
         $opco_response='';

         $routing = $this->GetOperatorRouting($data['operator_id'],'status');
          $xml = $this->WriteGeneralXMLFile($routing[0], $data,$log_name);

          $opco_response = $this->SendByCURL($routing[0]['routing_url'], $data['transaction_type'],$xml,$log_name);
           $array= $this->map->FormatXMLTOArray($opco_response);
           $error_codes=$this->MatchOPeratorRespcodes($array['operator_status']);
           $combined =array_merge($array,$error_codes);

          if($combined['transaction_status']=='failed'||$combined['transaction_status']=='completed'){

        $this->PrepareTOCloseTransaction($combined,$data,$log_name);

            }


         	}

           function PrepareTOCloseTransaction($trans_resp_array,$transaction,$log_name){


             $this->CloseTransaction($log_name,$transaction,$trans_resp_array);
             $transact = $this->GetTransaction($transaction['transaction_id']);

        $this->log->LogRequest($log_name, "AirtelrwandaModel::PrepareTOCloseTransaction closed transaction ".var_export($transact[0], true), 2, 3);


                $this->SendMerchantCompletedRequest($transact[0],$log_name);


       	}

}