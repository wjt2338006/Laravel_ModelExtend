<?php
/**
 * User: keith.wang
 * Date: 16-10-20
 */

namespace Keith\ModelExtend;


class Quick
{
    static public function checkoutConnection($connection, $call)
    {
        try
        {
            if (!is_callable($call))
            {
                throw new \Exception("checkoutConnection 传入了一个不可调用对象");
            }

            $connection = ModelExtend::compileConnectionString($connection);
            ModelExtend::saveConnection();
            ModelExtend::setConnection($connection["con"]);
            ModelExtend::setTable($connection["table"]);
            ModelExtend::setPrimaryKey($connection["field"]);
            $r = $call();
            return $r;
        } catch (\Exception $e)
        {
            throw $e;
        } finally
        {
            ModelExtend::rollbackConnection();
        }
    }

    static public function lazyLoad()
    {

    }
}

/*
 调用示范
Quick::checkoutConnection("tour.someTable.id",function(){
    ModelExtend::select(["desc"=>true]);
});

 */
