<?php
/**
 * User: keith.wang
 * Date: 16-10-11
 * V 1.01.1
 */

namespace Keith\ModelExtend;


use Illuminate\Support\Facades\DB;

/**
 * Class ModelExtend
 * @package App\Libraries\Tour
 */
class ModelExtend
{

    /**
     * 定义模型数据库连接
     * @var
     */
    static protected $connection;

    /**
     * 定义表
     * @var
     */
    static protected $table;

    /**
     * 定义主键名
     * @var
     */
    static protected $primaryKey;


    /**
     * 实例化时传入主键值
     * @var
     */
    protected $id;

    /**
     * 单条数据，以数组形式访问
     * @var
     */
    protected $data;


    /**
     * 映射表
     * @var
     */
    static protected $syncMap;

    /**
     * 映射条件
     * @var void
     */
    protected $syncCondition;

    static protected $async = false;

    /*
        $queryLimit
        |-sort = 排序字段
        |-desc = 是否倒序true/false
        |-id = 按照某个id查询     //ud
        |-start = 查询开始条目
        |-num = 查询多少条
        |-select = ["xx as new","count(*) a snum"]需要哪些字段，不填是所有
        |-paginate = 是否使用laravel默认分页机制，需要使用填入每页条数
        |-where = [] //ud
            |- ["or","field","=","value"] //第一个and或者or是无效的（单个 and和 or是没有意义的）
            |- ["and","field,"=","value]
            |- ...
        |-whereIn = ["id",[1,2,3]]  //组合使用whereIn是加载where末尾，如果最后一个条件是or，那么很可能不是你要的效果，最好不要混用 //ud
        |-link = []
            |-["name","selfFiled","connection.table.field1"["queryLimit"]]
            |- ...
        |-resultConvert = function(&$dataArray){}
        |-pk = 手动设定主键，id字段将按照这个字段查询，仅在使用id的时候有效
        |-deleteEmpty =["name1","name2"...]那些如果为空删除

     */

    /**
     * 高级查询构造，参数见文档
     * @param $queryLimit
     * @param null $query 如果需要自定义；连接和表，这里可以传入
     * @return array 结果数组 ["status":true,"message":"","data":[],"total":10]
     * @throws \Exception
     */
    static public function select($queryLimit, $query = null)
    {
        //dump("查询限制");
        //dump($queryLimit);
        if (empty($query))
        {
            $query = static::getQuery();
        }

        //排序
        if (isset($queryLimit["sort"]))  //自定义字段排序
        {
            if (isset($queryLimit["desc"]) && true == $queryLimit["desc"])
            {
                $query->orderBy($queryLimit["sort"], "desc");
            }
            else
            {
                $query->orderBy($queryLimit["sort"]);
            }

        }
        else    //默认使用按id排序
        {
            if (isset($queryLimit["desc"]) && true == $queryLimit["desc"])
            {
                if (isset($queryLimit["pk"]))
                {
                    $query->orderBy($queryLimit["pk"], "desc");
                }
                else
                {
                    $query->orderBy(static::$primaryKey, "desc");
                }

            }
        }

        //按主键id查找某条记录
        if (isset($queryLimit["id"]))
        {
            static::selectId($queryLimit, $query);
        }
        //设定where条件
        if (isset($queryLimit["where"]))
        {
            static::selectWhere($queryLimit["where"], $query);
        }
        //设定whereIn条件
        if (isset($queryLimit["whereIn"]))
        {
            static::selectWhereIn($queryLimit["whereIn"], $query);
        }


        //自定义方法
        static::selectExtra($queryLimit, $query);

        $returnData = [];
        //计算出符合条件的查询总条数,除开num和limit
        $numQuery = clone $query;//克隆出来不适用原来的对象
        $returnData["total"] = $numQuery->select(DB::raw('count(*) as num'))->first()->num;


        //根据开始和每页条数筛选结果
        if (isset($queryLimit["start"]))
        {
            $query->skip($queryLimit["start"]);
        }
        if (isset($queryLimit["num"]))
        {
            if ($queryLimit["num"] == 0)
            {
                $returnData["status"] = true;
                $returnData["message"] = "查询到数据,但num设为了0";
                $returnData["data"] = [];
                return $returnData;
            }

            $query = $query->take($queryLimit["num"]);
        }

        //筛选个别字段
        if (isset($queryLimit["select"]))
        {
            if (!is_array($queryLimit["select"]))
            {
                throw new \Exception("select语句，条件必须是一个数组");
            }

            $select = "";
            foreach ($queryLimit["select"] as $k => $v)
            {
                if ($k > 0)
                {
                    $select .= ",";
                }
                $select .= $v;

            }
            $select .= " ";

            //如果是空的
            if (empty($queryLimit["select"]))
            {
                $select = "*";
            }
            $query->select(DB::raw($select));
        }


        //是否使用laravel默认的分页机制,并处理结果
        $data = [];
        if (isset($queryLimit["paginate"]))
        {
            if (isset($queryLimit["link"]))
            {
                throw new \Exception("paginate与link不能共存");
            }
            $data = $query->paginate($queryLimit["paginate"]);
        }
        else
        {
            $data = $query->get();
            foreach ($data as $k => $v)
            {
                $data[$k] = (array)$v;
            }
        }
        //dump("查询构造将会执行的sql");
        //dump($query->toSql());
        //执行连表
        if (isset($queryLimit["link"]) && !empty($data))
        {

            static::linkTable($data, $queryLimit["link"]);
        }

        //对接收到的数据进行处理
        if (isset($queryLimit["resultConvert"]) && is_callable($queryLimit["resultConvert"]))
        {
            $queryLimit["resultConvert"]($data);
        }


        foreach($data as $k=>&$singleData)
        {
            //删除没有指定字段的项目
            if(isset($queryLimit["deleteEmpty"]))
            {
                foreach($queryLimit["deleteEmpty"] as $v)
                {
                    if(empty($singleData[$v]))
                    {
                        unset($data[$k]);
                    }
                }
            }
            //查询后数据过滤
            static::selectFilter($singleData); //过滤数据
        }


        //返回结果
        $returnData["status"] = true;
        $returnData["message"] = "成功获取到数据";
        $returnData["data"] = $data;
        return $returnData;

    }


    /**
     * 模型实例化,传入id
     * ModelExtend constructor.
     * @param $id
     */
    public function __construct($id)
    {
        $this->id = $id;
        $this->syncFromDataBase();


        $this->syncCondition = $this->loadSyncCondition();

        if (empty(static::$syncMap))
        {
            static::$syncMap = static::loadSyncMap();
        }

    }


    /**
     * 添加数据
     * @param $data //需要插入的数据
     * @return static //返回当前模型实例
     * @throws \Exception
     */
    public static function add($data)
    {
        if (empty(static::$syncMap))
        {
            static::$syncMap = static::loadSyncMap();
        }
        $query = static::getQuery();
        static::addExtra($data, $query);

        $id = $query->insertGetId($data);
        if ($id == 0)
        {
            throw new \Exception("数据插入失败");
        }
        return new static($id);
    }

    /**
     * add函数的一个包装，为了和以前的laravel模型保持一致
     * @param $data
     * @return ModelExtend
     */
    public static function create($data)
    {
        return static::add($data);
    }

    /**
     * add 和 update函数的封装 ，如果一个数据不存在（按照主键判定），则创建，否则修改
     * @param $data
     * @return ModelExtend
     * @throws \Exception
     */
    public static function createOrUpdate($data)
    {
        if(empty($data[static::$primaryKey]))
        {
            throw new \Exception("createOrUpdate 缺少主键，无法定位数据");
        }
        $query["id"] = $data[static::$primaryKey];
        $result = static::select($query)["data"];
        if(sizeof($result) > 0)
        {
            $model = new static($result[0][static::$primaryKey]);
            $model->update($data);
        }
        else
        {
            $model = static::add($data);
        }
    }


    /**
     * 删除方法，只会删除当前数据
     */
    public function delete()
    {
        $query = static::getQuery();
        $this->deleteExtra($query);

        $r = $query->where(static::$primaryKey, $this->id)->delete();
    }


    /**
     * 更新数据，更新后模型数据不是最新的
     * @param $data //需要更新的数据，如果为空，那么不会有动作
     */
    public function update($data)
    {
        if (empty($data))
        {
            return;
        }

        $query = static::getQuery();
        $this->updateExtra($data, $query);

        $r = $query->where(static::$primaryKey, $this->id)->update($data);

    }


    //兼容laravel的函数

    /**
     * 兼容老的laravel模型方法
     * @param array ...$args
     * @return mixed
     */
    public static function field(...$args)
    {
        $str = "";
        foreach ($args as $k => $v)
        {
            if ($k != 0)
            {
                $str .= " , ";
            }
            $str .= $v . " ";
        }
        return static::getQuery()->select(DB::raw($str));
    }

    /**
     * 兼容老的laravel模型方法
     * @param $field
     * @param bool $desc
     * @return mixed
     */
    public static function orderBy($field, $desc = false)
    {
        if ($desc)
        {
            return static::getQuery()->orderBy($field, $desc);
        }
        return static::getQuery()->orderBy($field);

    }

    /**
     * 兼容老的laravel模型方法
     * @param array ...$args
     * @return mixed
     */
    public static function where(...$args)
    {
        if (sizeof($args) == 2)
        {
            return static::getQuery()->where($args[0], $args[1]);
        }
        else
        {
            return static::getQuery()->where($args[0], $args[1], $args[2]);
        }

    }


    /**
     * 批量删除数据，按照queryLimit查询，删除匹配到的查询
     * @param $queryLimit //匹配查询限制
     * @param null $query //可以选择传入一个构造器，自定义连接和表
     * @param null $key //自定义连接和表以后，需要指定主键
     */
    public static function deleteMultiple($queryLimit, $query = null, $key = null)
    {
        if (empty($key))
        {
            if (isset($queryLimit["pk"]))
            {
                $key = $queryLimit["pk"];
            }
            else
            {
                $key = static::$primaryKey;
            }

        }

        $r = static::select($queryLimit, $query);
        //dump("批量删除这些数据");
        //dump($r);
        foreach ($r["data"] as $v)
        {
            if (!empty($query))
            {
                $q = clone $query;
                $q->where($key, $v[$key])->delete();
            }
            else
            {
                static::getQuery()->where($key, $v[$key])->delete();
            }

        }

    }


    /**
     * 按照QueryLimit多更新，匹配数据会被更新
     * @param $queryLimit //限制
     * @param $updateData //更新数据
     * @param null $query //可以选择传入一个构造器，自定义连接和表
     * @param null $key //自定义连接和表以后，需要指定主键
     */
    public static function updateMultiple($queryLimit, $updateData, $query = null, $key = null)
    {
        if (empty($key))
        {
            if (isset($queryLimit["pk"]))
            {
                $key = $queryLimit["pk"];
            }
            else
            {
                $key = static::$primaryKey;
            }

        }

        $r = static::select($queryLimit, $query);
        //dump("批量跟新匹配数据");
        //dump($r);
        foreach ($r["data"] as $v)
        {
            if (!empty($query))
            {
                $q = clone $query;
                $q->where($key, $v[$key])->update($updateData);
            }
            else
            {
                static::getQuery()->where($key, $v[$key])->update($updateData);
            }
        }
    }





    //同步函数
    /*
         同步分成
        1.条件  通过两套条件，匹配不同的数据
            本方条件直接通过id匹配，对端条件
            |-"connection.table.主键" = $queryLimit
            |-"connection.table.主键" = $queryLimit
            ......
            条件只有运行时才知道，所以模型只设定规则
            匹配发生在数据被修改前

        2.策略  匹配后，选择是删除重建,还是原地址修改，大多数情况下可以通过判断来解决这个问题
            |-添加，在添加后执行，检查老数据库是否有对应数据，如果有，执行更新，没有，添加
            |-更新，在更新后执行，会传入老的数据用来匹配，匹配项将会进行更新，queryLimit将会作为条件
            |-删除，在删除后执行，会传入老的数据用来匹配，匹配项会被删除
        3.映射
            映射分为可修改和不可修改的
            |-connection.table.主键
                |-field1 = selfField1
            |-connection.table.主键
                |-field1= [selfField2 ,function(&$data){}] //如果需要对数据修改，将会把本条数据传入

            设置映射在配置文件中
            实现映射在mapData函数中


         */
    /**
     * 同步添加，同步添加会根据映射关系去被同步库添加数据
     * @param $data //需要填入的数据，只支持单条数据
     * @param null $async // 是否启用异步
     * @return ModelExtend //返回这条数据生成的模型
     */
    public static function syncAdd($data, $async = null)
    {
        if ($async === null)
        {
            $async = static::$async;
        }
        if (empty(static::$syncMap))
        {
            static::$syncMap = static::loadSyncMap();
        }
        //dump("执行同步增加～～～～～～～～～～～～～～～～!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!");
        if (empty(static::$syncMap))
        {
            //执行本库添加
            $model = static::add($data);
            return $model;
        }
        $model = static::add($data);
        if ($async == false)
        {
            $model->asyncRunAdd();
        }
        else
        {
            $model->asyncSend($model->id, $model->conditionToString());
        }

        //dump("执行同步增加结束～～～～～～～～～～～～～～～～！！！！！！！！！！！！！！！！！！！！！！！！！！！！！！！！！");
        return $model;

    }

    /**
     * 异步运行同步，同步的同步数据也会使用这个函数，异步运行的需要在异步端运行这个函数
     * 添加不需要匹配条件
     */
    public function asyncRunAdd()
    {
        foreach (static::$syncMap as $k => $v)
        {
            //将数据匹配map取出，将数据映射上
            $insertData = $this->mapData($k);
            //dump("映射数据");
            //dump($insertData);

            //获取需要同步数据库链接
            $connectionData = static::compileConnectionString($k);
            $query = static::getBuilder($connectionData["con"])
                ->table($connectionData["table"]);
            $r = $query->insert($insertData);
            //dump("新增同步结果");
            //dump($r);

        }
    }

    /**
     * 同步更新，更新会根据条件匹配被同步库，如果没有数据，则会按照映射新加入，有数据会按照映射更新
     * @param $updateData //需要更新的参数数组
     * @param null $async //是否需要启用异步同步
     */
    public function syncUpdate($updateData, $async = null)
    {
        if ($async === null)
        {
            $async = static::$async;
        }
        //dump("开始同步更新～～～～～～～～～～～～～～～～～～～————————————————————————————————————————————————");
        $otherLimit = $this->syncCondition;
        if (empty($otherLimit))
        {
            //执行本库修改
            $this->update($updateData);
            $this->syncFromDataBase();
            return;
        }

        //执行本库修改
        $this->update($updateData);
        $this->syncFromDataBase();//获得最新数据


        //遍历每个表的规则
        if ($async == false)
        {
            $this->asyncRunUpdate($this->syncCondition, false);
        }
        else
        {
            $this->asyncSend($this->id, $this->conditionToString());
        }

        //dump("结束同步更新～～～～～～～～～～～～～～～～！！！！！！！！！！！！！！！！！！！！！！！！！！！！！！！！！！！！！");

    }

    /**
     * 异步运行同步，同步的同步数据也会使用这个函数，异步运行的需要在异步端运行这个函数
     * @param $condition //条件
     * @param bool $isStr //条件是否是一个字符串条件配置，通常在异步端调用时都是一个字符串
     */
    public function asyncRunUpdate($condition, $isStr = true)
    {
        if ($isStr)
        {
            $this->conditionFromString($condition);
        }

        $otherLimit = $this->syncCondition;
        foreach ($otherLimit as $k => $v)
        {
            //dump("获取规则----------------------------------------");
            //dump($k);
            //根据规则匹配数据
            $resultData = static::matchData($k, $v);
            //dump("匹配数据");
            //dump($resultData);

            //将数据匹配map取出，将数据映射上,得到需要插入到老数据库的东西
            $insertData = $this->mapData($k);
            //dump("获取映射数据");
            //dump($insertData);


            //获取需要同步数据库链接
            $connectionData = static::compileConnectionString($k);
            $query = static::getBuilder($connectionData["con"])
                ->table($connectionData["table"]);

            //被同步方有没有匹配数据
            if (sizeof($resultData["data"]) == 0)
            {
                //dump("更新 无源数据 同步加入数据");
                //没有该匹配数据的行为，一次纯天然的添加

                //执行添加
                $query->insert($insertData);
            }
            else
            {
                //dump("更新 有源数据 同步更新数据");
                //注意这里会把主键注入到里面方便使用功能
                $v["pk"] = $connectionData["field"];
                //可能其他的表已经添加了这一条数据，我们需要原处更新
                //执行更新
                static::updateMultiple($v, $insertData, $query, $connectionData["field"]);
            }
        }

    }


    /**
     * 同步删除，会根据条件匹配，匹配后删除
     * @param null $async 异步执行
     */
    public function syncDelete($async = null)
    {
        if ($async === null)
        {
            $async = static::$async;
        }
        //dump("同步删除～～～～～～～～～～～～～～～————————————————————————————————————————————————————————————");

        if (empty($this->syncCondition))
        {
            //dump("没有条件");
            //执行本库删除
            $this->delete();
            return;
        }

        //执行本库删除
        $this->delete();

        if ($async == false)
        {
            static::asyncRunDelete($this->syncCondition, false);
        }
        else
        {
            $this->asyncSend($this->id, $this->conditionToString());
        }

        //dump("同步删除结束～～～～～～～～～～～～～！！！！！！！！！！！！！！！！！！！！！！！！！！！！！！！！！！！！！！！");
    }

    /**
     * 异步运行同步，同步的同步数据也会使用这个函数，异步运行的需要在异步端运行这个函数
     * 注意删除的异步是静态的,因为在异步端数据可能已经被删除了
     * @param $condition //条件
     * @param bool $isStr //条件是否是一个字符串条件配置，通常在异步端调用时都是一个字符串
     */
    public static function asyncRunDelete($condition, $isStr = true)
    {
        if ($isStr)
        {
            $condition = static::conditionFromStringStatic($condition);
        }

        $otherLimit = $condition;
        //遍历每个表的规则
        foreach ($otherLimit as $k => $v)
        {
            //dump("规则 " . $k);
            //根据规则匹配数据
            $resultData = static::matchData($k, $v);
            //dump("匹配数据 ");
            //dump($resultData);

            //不再需要映射

            //获取需要同步数据库链接
            $connectionData = static::compileConnectionString($k);
            $query = static::getBuilder($connectionData["con"])
                ->table($connectionData["table"]);

            //被同步方有没有匹配数据
            if (sizeof($resultData["data"]) == 0)
            {
                //dump("没有匹配数据");
                //匹配失败,没有数据会被删除
            }
            else
            {
                //dump("有匹配数据");
                $v["pk"] = $connectionData["field"];
                //将匹配的数据删除
                static::deleteMultiple($v, $query, $connectionData["field"]);
            }

        }
    }

    /**
     * 如果需要异步化，请从新实现这个函数，发送异步请求
     * @param int $id //当前这条数据主键
     * @param string $condition //条件字符串
     */
    public function asyncSend($id, $condition)
    {

    }


    //同步条件映射设置函数
    /*
    1.条件  通过两套条件，匹配不同的数据
    本方条件直接通过id匹配，对端条件
    |-"connection.table.主键" = $queryLimit
    |-"connection.table.主键" = $queryLimit
    ......
    条件只有运行时才知道，所以模型只设定规则
    匹配发生在数据被修改前

    条件和映射要一一对应
    下面是示范，一个模型如果需要同步，应该覆盖该方法
    */
    /**
     * 在这里返回初设条件，这些条件是同类型模型通用
     */
    public function loadSyncCondition()
    {
        /*
        return
            [
                "where" =>
                    [
                        ["or", "field", "=", "value"],
                        ["and", "field", "=", "value"],
                    ]
            ];
        $this->syncCondition = [
            "tour.product.product_id" => $productLimit
        ];
        */
    }

    //追加一个条件
    /**
     * 添加同步条件
     * @param $connection //必须是 连接.表.主键 且要有对应映射
     * @param $queryLimit //限制被同步方的条件
     */
    public function appendSyncCondition($connection, $queryLimit)
    {
        $this->syncCondition[$connection] = $queryLimit;
    }

    /**
     * 清理同步条件
     */
    public function cleanSyncCondition()
    {
        $this->syncCondition = [];
    }

    /*
    3.映射
    映射分为可修改和不可修改的
    |-connection.table.主键
        |-field1 = selfField1
    |-connection.table.主键
        |-field1= [selfField2 ,function(&$data){}] //如果需要对数据修改，将会把本条数据传入

    条件和映射要一一对应
    下面是示范，一个模型如果需要同步，应该覆盖该方法
     */
    /**
     * 返回映射关系表
     */
    public static function loadSyncMap()
    {
        /*
        return [
            "tour.product.product_id" => ["product_some_field" => "my_table_field"],
            "tour.product.product_id" => [
                "product_some_field" => [
                    "my_table_field",
                    function (&$data)
                    {
                        return $data["my_table_field"] * 99;
                    }
                ]
            ],
        ];
        */
    }



    //辅助函数


    /**
     * 获取本连接表的查询
     * @return mixed
     */
    public static function getQuery()
    {
        return app("db")->connection(static::$connection)->table(static::$table);
    }

    /**
     * 获取本连接的查询
     * @param $connection
     * @return mixed
     */
    public static function getBuilder($connection)
    {
        return app("db")->connection($connection);
    }

    /**
     * 查询数据库中的最新本条数据，更新内存中本条的数据
     * @throws \Exception
     */
    public function syncFromDataBase()
    {
        $this->data = (array)(static::getQuery()->where(static::$primaryKey, $this->id)->first());
        if (empty($this->data))
        {
            throw new \Exception("没有这一条记录," . static::$table . " id=" . $this->id);
        }
    }


    /**
     * 在获得数据的时候格式化一部分数据
     * @param $data //会传入每一条数据
     */
    public static function selectFilter(&$data)
    {

    }


    /**
     * 取出本条数据
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * 取出id
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * 转换天数到时间戳
     * @param $dayStr //如 1970-01-02
     * @return int
     */
    public static function timeFromDayStr($dayStr)
    {
        $zone = new \DateTimeZone(env("TIMEZONE", "Asia/Chongqing"));
        $date = \DateTime::createFromFormat('Y-m-d H:i:s',
            $dayStr . " 00:00:00", $zone)->getTimestamp();
        return $date;
    }

    /**
     * 转换具体秒到时间戳
     * @param $str //如 1970-01-02 00:00:00
     * @return int
     */
    public static function timeFromSecondStr($str)
    {
        $zone = new \DateTimeZone(env("TIMEZONE", "Asia/Chongqing"));
        $date = \DateTime::createFromFormat('Y-m-d H:i:s',
            $str, $zone)->getTimestamp();
        return $date;
    }

    /**
     * 从时间戳转换为 秒 格式 1970-01-02 00:00:00
     * @param $time
     * @return false|string
     */
    public static function timeToSecondStr($time)
    {
        date_default_timezone_set(env("TIMEZONE", "Asia/Chongqing"));
        return date('Y-m-d H:i:s', $time);
    }

    /**
     * 从时间戳转换为 天 格式 1970-01-02
     * @param $time
     * @return false|string
     */
    public static function timeToDayStr($time)
    {
        date_default_timezone_set(env("TIMEZONE", "Asia/Chongqing"));
        return date('Y-m-d', $time);

    }


    //自定义方案,可以在继承方案中被覆盖
    /**
     * 额外查询，可以自定义一些查询类型，在排序，决定条数之前调用
     * @param $queryLimit //传入的筛选函数
     * @param $query //查询构造器
     */
    protected static function selectExtra(&$queryLimit, $query)
    {

    }

    /**
     * 额外添加，在添加之前调用
     * @param $data //会被添加的数据
     * @param $query //查询构造器
     */
    protected static function addExtra(&$data, $query)
    {

    }

    /**
     * 额外更新，在更新之前调用
     * @param $data
     * @param $query
     */
    protected function updateExtra(&$data, $query)
    {

    }

    /**
     * 额外删除，在删除之前调用
     * @param $query
     */
    protected function deleteExtra($query)
    {

    }


    //同步内部调用
    /**
     * 建立匹配
     * @param $willMatch //需要匹配的链接，配置中的名字
     * @param $queryLimit //匹配限制，规则同select
     * @return array
     */
    protected static function matchData($willMatch, $queryLimit)
    {
        $connectionData = static::compileConnectionString($willMatch);
        $query = static::getBuilder($connectionData["con"])
            ->table($connectionData["table"]);
        $queryLimit["pk"] = $connectionData["field"];
        $resultData = static::select($queryLimit, $query);
        return $resultData;
    }


    /**
     * 建立新老映射
     * @param $willMap //要使用的映射关系，配置中的名字
     * @return array    //返回映射后可以插入的数据
     * @throws \Exception
     */
    protected function mapData($willMap)
    {
        if (!isset(static::$syncMap[$willMap]))
        {
            throw new \Exception("没有这个映射关系 " . $willMap);
        }
        //将数据匹配map取出，将数据映射上
        $map = static::$syncMap[$willMap];
        $insertData = [];
        foreach ($map as $mapK => $mapV)
        {
            if (is_array($mapV) && is_callable($mapV[1]))
            {
                $insertData[$mapK] = $mapV[1]($this->data);
            }
            else
            {
                $insertData[$mapK] = $this->data[$mapV];
            }

        }
        return $insertData;
    }


    //内部调用方法
    /**
     * 编译字符串连接信息到真实的链接
     * @param $connectionData
     * @return array
     * @throws \Exception
     */
    public static function compileConnectionString($connectionData)
    {
        $con = null;
        $table = null;
        $field = null;
        $argList = explode(".", $connectionData);
        if (!is_array($argList) || sizeof($argList) > 3 || sizeof($argList) < 2)
        {
            throw new \Exception("错误的连接信息 " . $connectionData . " 参数表：" . json_encode($argList));
        }
        if (sizeof($argList) == 3)
        {
            $con = $argList[0];
            $table = $argList[1];
            $field = $argList[2];
        }
        else //为2
        {
            $con = static::$connection;
            $table = $argList[0];
            $field = $argList[1];
        }
        return ["con" => $con, "table" => $table, "field" => $field];
    }

    /**
     * 将条件从原格式转换为字符串
     * @return string
     */
    public function conditionToString()
    {
        $conditionStr = json_encode($this->syncCondition);
        return $conditionStr;
    }

    /**
     * 将条件从字符串转回原格式
     * @param $conditionStr
     * @return //条件数组
     */
    public function conditionFromString($conditionStr)
    {
        return $this->syncCondition = json_decode($conditionStr, true);
    }

    /**
     * 用于静态调用的条件获取
     * @param $conditionStr
     * @return mixed
     */
    public static function conditionFromStringStatic($conditionStr)
    {
        return json_decode($conditionStr, true);
    }

    //select会调用到的函数

    /**
     * @param $where
     * @param $query
     * @throws \Exception
     */
    protected static function selectWhere($where, $query)
    {
        if (!is_array($where))
        {
            throw new \Exception("where语句，条件必须是一个数组");
        }
        foreach ($where as $v)
        {
            if ($v[0] == "or")
            {
                $query->orWhere($v[1], $v[2], $v[3]);
            }
            else
            if ($v[0] == "and")
            {
                $query->where($v[1], $v[2], $v[3]);
            }
            else
            {
                $query->where($v[0], $v[1], $v[2]);
            }


        }
    }
    //ok
    /**
     * @param $where
     * @param $query
     * @throws \Exception
     */
    protected static function selectWhereIn($where, $query)
    {
        if (!is_array($where))
        {
            throw new \Exception("whereIn语句，条件必须是一个数组");
        }

        $query->whereIn($where[0], $where[1]);
    }

    /**
     * @param $queryLimit
     * @param $query
     */
    protected static function selectId($queryLimit, $query)
    {
        if (isset($queryLimit["pk"]))
        {
            $query->where($queryLimit["pk"], "=", $queryLimit["id"]);
        }
        else
        {
            $query->where(static::$primaryKey, "=", $queryLimit["id"]);
        }

    }

    /*
   |-link = []
           |-["name","selfFiled","connection.table.field1"["queryLimit"]]
           |- ...

   */
    /**
     * 连表查询调用方法
     * @param $data
     * @param $links
     * @throws \Exception
     */
    protected static function linkTable(&$data, &$links)
    {
        $linkField = [];
        $linkLimit = [];
        $linkConnection = [];
        $linkSelect = [];
        $linkName = [];
        $linkResult = [];

        //遍历每一条规则
        foreach ($links as $link)
        {
            if (isset($data[$link[0]]))
            {
                throw new \Exception("数据已经存在这个字段 $link[0] 不能再将其作为子数据名");
            }
            $name = $link[0];
            $selfFiled = $link[1];
            $connectionStr = $link[2];

            //存self field
            $linkSelf[$connectionStr] = $selfFiled;

            //存附加select
            $select = [];
            if (isset($link[3]))
            {
                $select = $link[3];
            }
            $linkSelect[$connectionStr] = $select;

            //存连接
            $connectionData = static::compileConnectionString($connectionStr);
            $linkConnection[$connectionStr] = static::getBuilder($connectionData["con"])
                ->table($connectionData["table"]);

            //存对面的域
            $linkField[$connectionStr] = $connectionData["field"];

            //存子数组名
            $linkName[$connectionStr] = $name;

            //遍历每一条数据 存赛选数据
            foreach ($data as &$singleData)
            {
                $linkLimit[$connectionStr][] = $singleData[$selfFiled];
            }

        }

        //只会有按照link数查询 = m[ n ]
        foreach ($linkConnection as $k => $query)
        {
            $query->where(function ($query) use ($k, $linkLimit, $linkField)
            {
                foreach ($linkLimit[$k] as $limitValue)
                {
                    $query->orWhere($linkField[$k], "=", $limitValue);
                }
            });
            //迭代下一层link数  m[ n+1 ]
            $linkResult[$k] = static::select($linkSelect[$k], $query)["data"];
            foreach ($data as &$singleData)
            {
                foreach ($linkResult[$k] as $sonData)
                {
                    if ($singleData[$linkSelf[$k]] == $sonData[$linkField[$k]])
                    {
                        $singleData[$linkName[$k]][] = $sonData;
                    }
                }
            }
        }
        //总的查询数  m[n]表示第n层的link数   m[0] * m[1] * m[2] ......


    }


}