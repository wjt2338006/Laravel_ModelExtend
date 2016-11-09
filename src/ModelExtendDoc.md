# ModelExtend 文档 1.01.7
## 更新
####1.01.1 2016-10-19   
连表递归改为迭代，查询次数为  (m[n]表示第n层的link数)   m[0] * m[1] * m[2] ......  
create,createOrUpdate方法加入，适应laravel的习惯     
提供了几个laravel model兼容的接口  
####1.01.2 2016-10-20  
提供了一个适配类Quick，用来在老代码中使用模型  
select返回码变为200 
增加filter，用来过滤字段
为了实现Quick加入了一些方法在ModelExtend中。
在Helper中加入了触发错误的一些函数
####1.01.3 2016-10-24
修改了select中数据过滤resultConvert的Bug，现在只会传一条数据了
完善了Helper中的一些方法
####1.01.4 2016-10-25
select可以使用laravel自带的分页了，返回值中的page
####1.01.5 2016-10-26
将select内部所有的isset改为!empty
不再支持field兼容方法
####1.01.6 2016-10-27
select增加first 并支持link
select时 link name若为空，则下一级别数据将嵌入到当前条中，如join一样
解决多连接查询的冲突情况,两个连接会重叠在一起，

####1.01.7 2016-11-2
同步类改版
select删除选项修复bug

####1.01.8 2016-11-7
修改一些同步类的功能,使得其适应最新同步逻辑
修复link二级查询时候first的问题

####1.02   2016-11-9


## 简述
## 初始化
初始化时，直接继承ModelExtend，并且覆盖几个参数
这些参数是必须要覆盖的:
```
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
```
    
这里给出一个例子:
```
    class Product extends ModelExtend
    {
        static protected $connection = "tour";
        static protected $table = "product";
        static protected $primaryKey = "product_id";
    }
```
    
    
## 查询
### 基础查询
ModelExtend提供了足够动态的查询构造器，只需要简单的定义一个数组，就能够查询到指定内容
```
$queryLimit =
         [
             "desc" => true,    //倒序
             "start" => 0,      //从第几条开始
             "num" => 10,       //需要多少条
             "sort" => "provider_id",   //按照什么字段排序
             "where"=>          //where条件
             [
                 ["and","provider_id","=",4],
                 ["or","provider_id","=",5]
             ],
             "whereIn" =>       //whereIn条件
             [
                 "option_id",
                 [33, 34, 35]
             ]
         ],
$data = TestModel::select($queryLimit);
dump($data["data"]);
```
支持以下参数，参数不填的话会使用默认值或者不设条件。
```
     $queryLimit
     |-sort = 排序字段
     |-desc = 是否倒序true/false
     |-id = 按照某个id查询     //ud
     |-start = 查询开始条目
     |-num = 查询多少条
     |-select = 需要哪些字段，不填是所有,如["xx as new","count(*) a snum"]
     |-paginate = 是否使用laravel默认分页机制，需要使用填入每页条数 （不可以和连表一起使用，数据结构不同，第二维是对象，不建议使用）
     |-where = [] //ud
         |- ["or","field","=","value"] //第一个and或者or是无效的（单个 and和 or是没有意义的）
         |- ["field,"=","value] //默认使用and语法
         |- ...
     |-whereIn = ["id",[1,2,3]]  //组合使用whereIn是加载where末尾，如果最后一个条件是or，那么很可能不是你要的效果，最好不要混用 //ud
     |-link = []
         |-["name","selfFiled","connection.table.field1"["queryLimit"]]
         |- ...
     |-resultConvert = function(&$dataArray){} //对结果进行转换，会传入本条结果的引用
     |-pk = 手动设定主键，id字段将按照这个字段查询，仅在使用id的时候有效
     |-deleteEmpty =["name1","name2"...]那些如果为空删除,删除条数时total不会跟着删除。（前端分页的时候不建议使用这个功能）
     |-first = true 只查询第一条数据,可以和link一起使用,效果最佳
     
```
返回结果数组 
```
return 
[
    "status":200,  //是否成功
    "message":"",   //返回信息
    "data":[],      //返回数据，全数组格式
    "total":10      //按照此条件匹配，共计多少条
]
```
### 连表查询
   对于复杂的业务逻辑，通常需要多重连表查询，且数据结构需要层级的关系，使用link字段可以达到这个要求
   
   ````   
   |-link = []
         |-["name","selfField","connection.table.field1"["queryLimit"]]
         |- ...
   ````
   使用了这个结构并传入select以后，下一张表的数据会拼接到本次返回数据中的name字段里  
   关联关系是selfField == connection.table.field1（表示意思是 数据库连接.表.字段 连接字段可以省略，默认使用当前连接）  
   如果还需要附加关系，将新的queryLimit传入即可（新的queryLimit也可以包含link，实现递归查询）  
   
```
    $queryLimit["link"] =
    [
        [
            "value",//子数组名
            "option_id",//本方关联
            "tour.product_upgrade_option_value.option_id",//对方关联
            [
                "link" => [
                    ["operation", "value_id", "product_upgrade_option_value_operation.value_id"]
                ]
            ] //queryLimit
        ],
    ];
    SomeModel::select($queryLimit);
```
上面是一个例子，连表查询了两次，获取数据后，新的子数据在本数据的value底下，每一条value底下有一个operation字段  
里面有有匹配的operation数据:
### 查询扩展
   我们可以通过继承selectExtra方法来自定义查询构造参数，注意会传入两个参数，$queryLimit必须使用引用&，不然不会起作用
```
//在某个模型类中
    public static function selectExtra(&$queryLimit, $query)
    {
       if (isset($queryLimit["specialDate"]))
       {
           $query->where("create_at", ">", "1476322090");
       }
    
    }
```
这里我们新增了一个specialDate参数，只要传入了specialDate，我们就会查询大于一个时间的数据  
通过这种方法，可以根据不同表的需求自定义自己模型  
这个函数在排序，决定查询条数之前会被调用
    有时候我们需要对查询出的每一条结果进行参数过滤，可以通过覆写selectFilter实现，下面这个例子我们对每一条数据的时间进行了转换，变成字符串
```
    public static function selectFilter(&$data)
    {
        $data["created_at"] = static::timeToSecondStr($data["created_at"]);
        $data["updated_at"] = static::timeToSecondStr($data["updated_at"]);
    }
```
### 兼容laravel的函数
为了照顾laravel使用者习惯，可以使用以下几个传统查询
```
        Model::field("name",'stock_product_id')->get(); //相当于原来的select
        
        Model::where("stock_product_id",'=',1)->get();
        Model::where("stock_product_id",1)->get();
        
        Model::orderBy("stock_product_id","desc")->get();
        Model::orderBy("stock_product_id")->get();
```
## 添加，删除，修改
查询，删除，修改动作基本沿用laravel的习惯，这里直接给出例子
```
$r = TestModel::add(
        [
            "provider_id" => 5,
            "name" => "xxx",
            "tips" => "xxx",
            "can_multi_select" => 0,
            "required_no" => 0,
            "old_parent_id" => 30166,
            "subname" => "xx"
        ]);
        
$r->update(["provider_id" => 6]);

$r->delete();
```
同时也支持多更新，删除方法，会根据queryLimit匹配条目，并执行操作  
```
    /**
     * 批量删除数据，按照queryLimit查询，删除匹配到的查询
     * @param $queryLimit //匹配查询限制
     * @param null $query //可以选择传入一个构造器，自定义连接和表
     * @param null $key //自定义连接和表以后，需要指定主键
     */
    static function deleteMultiple($queryLimit, $query = null, $key = null)
    //someModel::deleteMultiple($ql)
    
    /**
     * 按照QueryLimit多更新，匹配数据会被更新
     * @param $queryLimit //限制
     * @param $updateData //更新数据
     * @param null $query //可以选择传入一个构造器，自定义连接和表
     * @param null $key //自定义连接和表以后，需要指定主键
     */
    static function updateMultiple($queryLimit, $updateData, $query = null, $key = null)
    //someModel::updateMultiple($ql)
```


同样，增删改也有自定义方法
定义额外的附加方法：
```
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
```

同时为了适应laravel原有model的习惯，我们也有createOrUpdate函数等提供习惯的调用  
```
/**
 * add函数的一个包装，为了和以前的laravel模型保持一致
 * @param $data
 * @return ModelExtend
 */
public static function create($data)

/**
 * add 和 update函数的封装 ，如果一个数据不存在（按照主键判定），则创建，否则修改
 * @param $data
 * @return ModelExtend
 */
public static function createOrUpdate($data)


```
### 过滤函数
提供一个过滤函数，用来过滤添加数据时的字段
```
    /**
     * 过滤字段
     * @param $data //过滤的数据
     * @param $fieldList //需要过滤字段
     * @param bool $isForbid true //删除这些字段，false保留这些字段
     * @return array //过滤后的数据
     * @throws \Exception
     */
     //例子
     ModelExtend::filter($price,["product_type_id","gross_profit","transaction_fee","price","product_price_id"]);
     
```
$data是引用传入的，不是必须要写返回值来接受过滤后数据。
## 便利函数
SomeModel::getQuery()           获取本连接表的查询   
$someModel->syncFromDataBase()  刷新数据  
$someModel->getData()           取出本条数据  
$someModel->getId()             取出主键   
SomeModel::timeFromSecondStr("1970-01-02 00:00:00")   转换具体秒到时间戳
SomeModel::timeFromDayStr("1970-01-02") 转换天数到时间戳    
SomeModel::timeToSecondStr($time)   从时间戳转换为 秒 格式 1970-01-02 00:00:00   
SomeModel::timeToDayStr($time)      从时间戳转换为 天 格式 1970-01-02  
时区使用env中的TIMEZONE,默认为Asia/Chongqing
## 同步
 数据同步是一个令人头疼的话题，特别是不同的数据结构之间的同步，ModelExtend可以用一种优雅且自动化的方式来进行数据同步  
 而使用的方法就是简单的继承,请看下面的类
```
class TestModelDelSync extends ModelExtend
{
    static protected $connection = "test"; 
    static protected $table = "src_2";
    static protected $primaryKey = "src_id";

    //返回映射
    public static function loadSyncMap()
    {
        return 
        [
            "src_product.poduct_id" => 
            [
                "f1" => "some_field_1",
                "f2" => "some_field_2",
                "src_2_id" => "src_id"
                "stock.stock_product.stock_product_id" => function (&$data, &$insert, $selfObj)
                 {
                    //这种方法完全使用了自己的同步策略,执行了这些代码块后,自带的同步逻辑不会运行,参数为将要本方数据,插入的数据,本方对象
                    //smome logic
                 }
            ],
            "src_3.src_3_id" => 
            [
                "f1" => "some_field_1",
                "src_2_id" => function (&$data, &$insert, $selfObj){return 1}  //可以对一个数据传闭包,参数同上
            ]
        ];
    }
    public function __construct($id)
    {
        parent::__construct($id);
        
        //设定条件
        
        $srcProductLimit["whereIn"] = ["src_2_id", [$this->getId()]];
        $this->appendSyncCondition("src_product.poduct_id",$srcProductLimit); //设置和product的关系是src_2_id字段与自己的src_id字段相同
        
        $src3Limit["where"] = [ ["or", "src_2_id", "=", $this->getData()["src_id"]] ];
        $this->appendSyncCondition("src_3.src_3_id",$src3Limit);    //设置和src_3的关系是其中的src_2_id字段与自己的src_id相同
        
        //条件添加时第四个参数可以递归的加入另一个条件,其将会在该条件之后运行
        $this->appendSyncCondition("essential.product.id", $esLimit, function ($condition, $thisObj = null) use ($id)
        {
                $tickeLimit["id"] = 1;
              $thisObj->appendSyncCondition("ticket.ticket_product.product_id", $tickeLimit);
        });
    }
}

```
我们通过覆盖loadSyncMap()方法，为模型设定映射，返回一个映射数组，映射遵循如下格式(连接可以省略)
```
    "connection.table.主键" = ["对端字段"=>"本方字段",....],
    "table.主键" = ["对端字段"=>["本方字段",function(&$data){}],....] //如果需要对数据修改，将会把本条数据传入
```
设定了映射后，在每次实例化模型时，我们在构造函数中设定匹配条件，设定条件遵循如下格式，这里的$queryLimit请不要使用link
```
    $this->appendSyncCondition("essential.product.id", $queryLimit,function($condition, $thisObj = null){}); //后两个参数可选
```
一个条件必须要有对应的一条映射，否则会抛出错误,但是一个映射不一定需要一个条件,因为在内部实现中,是根据条件来进行同步的,有了条件以后,才回去寻找映射
  

 
然后在调用方.如某个控制器，需要同步增删改时，可以如下操作
```

//在某个控制器
//同步调用
$model = TestModelDelSync::syncAdd(["some_field_1" => "x10"]);
$model->syncUpdate(["some_field_2" => "x64"]);
$model->syncDelete();


//同时提供异步调用
//在异步端，先实例化本对象，然后调用异步同步方法,这里提供每种调用的例子
//要在模型中设定成员变量 static protected $async = true;

//添加
//在同步端
$model = TestModelDelSync::syncAdd(["some_field_1" => "x10"]); 
//通过通讯机制传递$id

                //在异步端
               
                $mode =new SomeModel($id) 
                $model->asyncRunAdd();
  
//更新
//在同步端             
$model->syncUpdate(["some_field_2" => "x64"]);
//通过通讯机制传递$condition和$id


                //在异步端          
                $mode = new SomeModel($id);
                $model->loadSyncCondition(); //需要加载条件   
                $model->asyncRunUpdate($condition);
                
                
//删除
//在同步端              
$model->syncDelete();
//通过通讯机制传递$condition,不需要id，因为可能这个模型实际上已经被删除，无法实例化
                //在异步端
                SomeModel::asyncRunDelete($condition);//异步调用也是用静态方法来实现
```
  
通过定义好上面的映射，设定条件，在增删改查时使用对应的同步数据版的函数，就会触发数据同步，其内部运行机制有以下步骤：   
1.匹配数据（删除，更新）  
2.建立映射（添加，更新）  
3.执行写入数据策略（添加：直接按照映射添加，删除：删除匹配数据，更新：有匹配 数据修改，无匹配 数据添加） 

对于需要异步同步数据的场景，你只需要按照示范， 设定成员变量   
```
static protected $async = true;    
```
并且重写发送函数，告诉类该如何发送这个异步请求（比如发送到nsq，或者redis）
```
/**
 * @param int $id   //当前这条数据主键
 * @param string $condition //条件字符串
 */
public function asyncSend($id,$condition)
{

}
```
然后还是按照原来的语法操作进行增删改



