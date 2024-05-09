<?php

namespace App\Models;

use DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plus extends Model
{
    use HasFactory;

    function getData()
    {
        $data = DB::table('Plus')
            ->select('*')
            ->join('Status', 'Status.IDStatus', '=', 'Plus.IDStatus')
            ->where("sir_sincronizar", 0)
            ->orderBy('plu_id', 'asc')
            ->limit(1)
            ->first();
        return $data;
    }

    function getInformacionData($tipo_dato_obtener, $tipo_busqueda, $idCadena, $idProducto, $parametro_busqueda = '')
    {
        $sqlSP = "EXEC config.PRODUCTOS_IA_obtener_informacion_plus $tipo_busqueda, $idCadena, $idProducto, '$parametro_busqueda'";

        if ($tipo_dato_obtener == 'array') {
            $resultado = DB::select($sqlSP);
        }

        if ($tipo_dato_obtener == 'row') {
            $resultado = DB::selectOne($sqlSP);
        }

        return $resultado;
    }

    public function consultaVariableVColleccionCadena($idCadena, $nombreColeccion, $nombreDato)
    {
        $lc_sql = "select [config].[fn_ColeccionCadena_VariableV] ('$idCadena','$nombreColeccion','$nombreDato') as ruta";
        $resultado = DB::selectOne($lc_sql);
        return $resultado->ruta;
    }

    function updateDataConsult($plu_id, $status)
    {
        /* sir_sincronizar
        0: Nuevo, sin sincronizar
        1: Sincronizado con el SIR
        2: Error al Sincronizar
        */
        DB::table('Plus')
            ->where('Plus.plu_id', $plu_id)
            ->update(['sir_sincronizar' => $status]);
    }
}