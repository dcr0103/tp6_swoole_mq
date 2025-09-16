<?php

namespace app\common\model;

use think\Model;

class DlxMessage extends Model
{
    protected $name = 'dlx_messages';
    protected $json = ['payload', 'headers'];
    protected $autoWriteTimestamp = 'datetime';
}
