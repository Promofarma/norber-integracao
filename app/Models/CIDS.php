<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CIDS extends Model
{
    protected $table = 'RH.CID_FOLHAS';

    protected $primaryKey = 'CID_FOLHA';

    protected $fillable = [
        'CID_FOLHA',
        'CODIGO_CID',
        'DESCRICAO'
    ];

    public $timestamps = false;
}
