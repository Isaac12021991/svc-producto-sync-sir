<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Plus;
use Illuminate\Support\Facades\Storage;


class PlusController extends Controller
{

    public function infoCurl($idCadena)
    {


        $Plus = new Plus();

        $filePath = storage_path('app/TokenApiSir.json');
        if (file_exists($filePath)) {
            $fileContent = file_get_contents($filePath);

            $data = json_decode($fileContent);
            $au = 'Authorization: ' . $data[0]->tokenType . ' ' . $data[0]->token;
        } else {

            $clienteSecret = $Plus->consultaVariableVColleccionCadena($idCadena, 'INTEGRACION SIR', 'CLIENT SECRET');
            $clienteId = $Plus->consultaVariableVColleccionCadena($idCadena, 'INTEGRACION SIR', 'CLIENT ID');
            $urlBase = $Plus->consultaVariableVColleccionCadena($idCadena, 'INTEGRACION SIR', 'URL BASE');
            $urlToken = $Plus->consultaVariableVColleccionCadena($idCadena, 'INTEGRACION SIR', 'ENDPOINT TOKEN');
            $url = $urlBase . $urlToken;

            if ($clienteId !== "N/A" && $clienteSecret !== "N/A" && $url !== "N/A") {
                $curl = curl_init();
                curl_setopt_array(
                    $curl,
                    array(
                        CURLOPT_URL => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => 2,
                        CURLOPT_POSTFIELDS => array(
                            "grant_type" => "client_credentials",
                            "client_id" => $clienteId,
                            "client_secret" => $clienteSecret
                        )
                    )
                );

                $response = curl_exec($curl);
                $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                curl_close($curl);

                if ($http_status === 200) {
                    $result = json_decode($response);
                    $result->urlBase = $urlBase;
                    $this->setToken($idCadena, $result);
                }

                if ($response === false) {
                   
                }
            }


        }

        $urlBase = $Plus->consultaVariableVColleccionCadena($idCadena, 'INTEGRACION SIR', 'URL BASE');
        $urlProducto = $Plus->consultaVariableVColleccionCadena($idCadena, 'INTEGRACION SIR', 'CREACION Y ACTUALIZACION PRODUCTOS');
        $prefijoPais = $Plus->consultaVariableVColleccionCadena($idCadena, 'INTEGRACION SIR', 'PREFIJO PAIS');
        // Save product SIR
        $url = $urlBase . $urlProducto;
        $url = $url . '?location=' . strtoupper($prefijoPais);

        $data['au'] = $au;
        $data['url'] = $url;

        return json_encode($data);

    }

    public function setToken($idCadena, $result)
    {
        $tokenList = [];
        $nuevoToken['idAssociated'] = $idCadena;
        $nuevoToken['token'] = $result->access_token;
        $nuevoToken['tokenType'] = $result->token_type;
        $nuevoToken['expiresAt'] = $result->expires_in;
        $nuevoToken['urlBase'] = $result->urlBase;

        array_push($tokenList, $nuevoToken);
        $content = json_encode($tokenList);
        Storage::disk('local')->put('TokenApiSir.json', $content);
        echo "<br> Token Success";
    }

    /**
     * Display a listing of the resource.
     */
    public function sync()
    {

        // Conexion a la base de datos
        $Plus = new Plus();
        $plus = $Plus->getData();

        if ($plus) {

            // Build data
            $dataArray = array();
            $dataArray['idProducto'] = $plus->plu_id;
            $dataArray['descripcion'] = $plus->plu_descripcion;
            $dataArray['preparacion'] = $plus->tiempo_preparacion;
            $dataArray['idTipoProducto'] = $plus->plu_tipo;

            // Obtener Clasificacion
            $dataClasificacion = $Plus->getInformacionData('row', 1, $plus->cdn_id, $plus->plu_id, $plus->IDClasificacion);
            $dataArray['idClasificacion'] = $dataClasificacion->idIntegracion;

            // Obtener Impresion plu
            $dataPluImpresion = $Plus->getInformacionData('row', 2, $plus->cdn_id, $plus->plu_id);
            $dataArray['plu_impresion'] = $dataPluImpresion->plu_impresion; // impesion plu 

            // Obtener Departamento
            $dataDepatamento = $Plus->getInformacionData('row', 3, $plus->cdn_id, $plus->plu_id);
            $dataArray['idDepartamento'] = $dataDepatamento->id_departamento;

            $dataArray['estado'] = $plus->std_descripcion;
            $dataArray['cdn_id'] = $plus->cdn_id;
            $dataArray['plu_num_plu'] = $plus->plu_id;

            // impuesto
            $dataImpuesto = $Plus->getInformacionData('array', 4, $plus->cdn_id, $plus->plu_id);
            $buildImpuesto = array();

            foreach ($dataImpuesto as $key => $value) {
                $data = explode("-", $value->IDImpuestos);
                array_push($buildImpuesto, array('descripcion' => $data[0], 'valor' => $data[1]));
            }

            $dataArray['impuestos'] = $buildImpuesto;

            $buildCategoria = array();
            $dataCategoria = $Plus->getInformacionData('array', 5, $plus->cdn_id, $plus->plu_id);
            foreach ($dataCategoria as $key => $value) {
                array_push($buildCategoria, array('idCategoria' => $value->idIntegracion, 'pvp' => $value->pr_pvp));
            }
            $dataArray['preciosPorCategoria'] = $buildCategoria;

            $dataPrecioFantasia = $Plus->getInformacionData('row', 6, $plus->cdn_id, $plus->plu_id);

            if (is_null($dataPrecioFantasia)) {
                $dataArray['jsonPreciosFantasia'] = '';
            } else {
                $dataArray['jsonPreciosFantasia'] = $dataPrecioFantasia->variableV;
            }

            // Get Endpoint
            $dataCurl = $this->infoCurl($plus->cdn_id);
            $dataCurl = json_decode($dataCurl);

            $header = array();
            $header[] = 'Content-type: application/json';
            $header[] = $dataCurl->au;
            $curl = curl_init();
            $metodo = 'POST';
            curl_setopt_array(
                $curl,
                array(
                    CURLOPT_CUSTOMREQUEST => $metodo,
                    CURLOPT_URL => $dataCurl->url,
                    CURLOPT_HTTPHEADER => $header,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_POSTFIELDS => json_encode($dataArray)
                )
            );

            $response = curl_exec($curl);
            $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            $result = json_decode($response);

            if ($http_status === 200) {
                $Plus->updateDataConsult($plus->plu_id, 1);
            } else if ($http_status === 500) {
                $Plus->updateDataConsult($plus->plu_id, 2);
            } else {
                $Plus->updateDataConsult($plus->plu_id, 2);
            }

        } 


    }




}