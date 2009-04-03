<?php
/*
The MIT License

Copyright (c) 2007 Tiago Bastos

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*/


define('__DEBUG__',true);

/*
 * Controller
 */
class C {
    var $layout = true;
    var $layout_tamplate = 'views/layout.php';
    var $headers;
    
    /* Render function return php rendered in a variable */
    public function render($file)
    {
        if ($this->layout==false){
            return $this->open_template($file); 
        } else {
           $this->content = $this->open_template($file); 
           return $this->open_template($this->layout_tamplate); 
        }
    }
    
    /* Open template to render and return php rendered in a variable using ob_start/ob_end_clean */
    private function open_template($name)
    {
        $vars = get_object_vars($this);
        ob_start();
        if (file_exists($name)){
            if (count($vars)>0)
                foreach($vars as $key => $value){
                    $$key = $value;
                }        
            require($name);
        } else {
            throw new Exception('View ['.$name.'] Not Found');
        }
        $out = ob_get_contents();
        ob_end_clean();
        return $out;
    }   
    
    /* Add information in header */
    public function header($text){
        $this->headers[] = $text;
    }    
    
    /* 
       Redirect page to annother place using header, 
       $now indicates that dispacther will not wait all process
    */
    public function redirect($url,$now=false)
    {
        if(!$now)
        $this->header("Location: {$url}");
        else header("Location: {$url}");
    } 
    
}

/*
 * Application core
 */
class NiceDog {
    var $routes = array();
    static private $instance = NULL ;
   
    function __construct()
    {
        if (isset($_GET['url']))
            $this->url =trim( $_GET['url'], '/');
        else $this->url = '';
    }
      
    /* Singleton */
    public function getInstance()
      {
        if(self::$instance == NULL)
        {
                self::$instance = new NiceDog();
        }
             return self::$instance;
       }   

    /* Add url to routes */
    public function add_url($rule, $klass, $klass_method, $http_method = 'GET')
    {
        $this->routes[] = array('/^' . str_replace('/','\/',$rule) . '$/', $klass,$klass_method,$http_method);
    }
    
    /* Process requests and dispatch */
    public function dispatch()
    {
        foreach($this->routes as $rule=>$conf) {
            if (preg_match($conf[0], $this->url, $matches) and $_SERVER['REQUEST_METHOD'] == $conf[3]){
                $matches = $this->parse_urls_args($matches);//Only declared variables in url regex
                $klass = new $conf[1]();
                ob_start();
                call_user_func_array(array($klass , $conf[2]),$matches);  
                $out = ob_get_contents();
                ob_end_clean();  
                if (count($klass->headers)>0){
                    foreach($klass->headers as $header){
                        header($header);
                    }
                } 
                print $out;                             
                exit();//Argh! Its not pretty, but usefull...
            }    
        }
        call_user_func_array('r404' , $_SERVER['REQUEST_METHOD']);  
    }   
    
    /* Parse url arguments */
    private function parse_urls_args($matches)
    {
        $first = array_shift($matches);
        $new_matches = array();
        foreach($matches as $k=>$match){
            if (is_string($k)){
                $new_matches[$k]=$match;
            }
        }
        return $new_matches;
    }
}
 
/*
 *  New routes,  just sugar!
 *  R('','Test','index','GET'); turns: 
 *  R('')->controller('test')->action('index')->on('GET');
 * Thanks to:  Rafael S. Souza <rafael.ssouza [__at__] gmail.com>
 */
 
function R($pattern){
       return new Route($pattern);
}
class Route{
    var $pattern;
    var $controller;    
    var $action;
    var $http_method = 'GET';
    function __construct($pattern){
        $this->pattern = $pattern;
        return $this;
    }
    
    function controller($controller){
        $this->controller = $controller;
        return $this;
    }
    
    function action($action){
        $this->action = $action;
        return $this;
    }
    
    function on($http_method){
        $this->http_method = $http_method;
        $this->bind();
        return $this;
    }
    
    function bind(){
        $router = NiceDog::getInstance()->add_url($this->pattern,$this->controller,$this->action,$this->http_method);
    }
}

/*
 * Run application
 */
function Run()
{
    try {
        NiceDog::getInstance()->dispatch();
    } catch (Exception $e) {
        if (__DEBUG__==true) {
        ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
    <head>
        <title>Error!</title>
    </head>
    <body>
        <h1>Caught exception: <?= $e->getMessage(); ?></h1>
        <h2>File: <?= $e->getFile()?></h2>
        <h2>Line: <?= $e->getLine()?></h2>
        <h3>Trace</h3>
        <pre>
        <?php print_r ($e->getTraceAsString()); ?>
        </pre>
        <h3>Exception Object</h3>
        <pre>
        <?php print_r ($e); ?>
        </pre>
        <h3>Var Dump</h3>
        <pre>
        <?php debug_print_backtrace (); ?>
        </pre>
    </body>
</html>        
        <?php
       } else {
        echo 'Oops';       
       }
    }

}
?>
