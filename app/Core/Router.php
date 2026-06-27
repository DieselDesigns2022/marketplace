<?php

namespace App\Core;
class Router
{
     private array $routes=[];
    public function get($p,$h)
    {
        $this->match(['GET'],$p,$h);

    }
    public function post($p,$h)
    {
        $this->match(['POST'],$p,$h);

    }
    public function match(array $m,string $p,array $h)
    {
        $this->routes[]=['m'=>$m,'p'=>$p,'h'=>$h];

    }
    public function dispatch(string $method,string $path): void
    {
        Helpers::verifyCsrf();
        $routeMethod = $method === 'HEAD' ? 'GET' : $method;
        foreach ($this->routes as $r) if (in_array($routeMethod,$r['m'],true))
        {
            $pattern = '#^' . preg_replace('#\{[^/]+\}#', '([^/]+)', $r['p']) . '$#';
            if (preg_match($pattern, $path, $matches))
           {
                array_shift($matches);
                [ $c,$fn ]=$r['h'];
                (new $c)->$fn(...$matches);
                return;

           }

        }
        Helpers::abort(404);

    }

}
