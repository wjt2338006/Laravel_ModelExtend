<?php
/**
 * User: keith.wang
 * Date: 16-10-20
 */

namespace Keith\ModelExtend;


use Illuminate\Support\Facades\Validator;

class Helper
{
    /**
     * 错误处理，在原来的错误基础上追加字符串
     * @param $msg
     * @param \Exception $e
     * @param bool $needMsg
     * @return string
     * @throws \Exception
     */
    public static function handleException($msg, \Exception $e, $needMsg = false)
    {
        $msg = $msg . " | " . $e->getFile() . " line " . $e->getLine() . " : " . $e->getMessage();
        if ($needMsg)
        {
            return $msg;
        }
        else
        {
            throw new \Exception($msg);
        }

    }

    /**
     * 数组类型判断,关联数组并不能通过判断
     * @param $v
     * @param string $msg
     * @throws \Exception
     */
    public static function isArray($v, $msg = "type is not array!")
    {
        if (is_array($v) && isset($v[0]))
        {
            return;
        }

        throw new \Exception($msg);
    }

    public static function emptyArray($array)
    {
        if (empty($array))
        {
            return [];
        }
        return $array;
    }


    /**
     * 空判断
     * @param $v
     * @param string $msg
     * @throws \Exception
     */
    public static function notEmpty($v, $msg = "")
    {
        if (empty($v))
        {
            $msg = "空类型 " . $msg;
            throw new \Exception($msg);
        }

    }


    /**
     * 废弃:建议使用ModelExtend中的Filter
     * 按照laravel框架的方法检查字段
     * @param $data //字段
     * @param $condition //laravel验证器规则
     * @throws \Exception
     */
    public static function checkData($data, $condition)
    {
        $validator = Validator::make($data,
            $condition);
        if ($validator->fails())
        {
            $err = "";
            $messages = $validator->errors();
            foreach ($messages->all() as $message)
            {
                $err .= $message;
            }
            throw  new \Exception($err);
        }
    }
}

class TypeMatchingException extends \Exception
{

}

class ModelExtendException extends \Exception
{

}