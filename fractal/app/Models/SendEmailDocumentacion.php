<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SendEmailDocumentacion extends Model
{
    use HasFactory;
    protected $table = 'send_email_documentacion';
    protected $primaryKey = 'ID_Send_email_doc';
    
    protected $fillable = [
        'id_cliente',
        'ID_Send_email_doc',
        'estado_envio_email',  
        'created_at', 
        'updated_at',
        
    ];

  

   
}
