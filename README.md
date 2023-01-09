# 简介

Webman Sail 是一个轻量级的命令行界面，用于 Webman 与 Docker 开发环境进行交互。Sail 为使用 PHP，MySQL 和 Redis 构建 Webman 应用程序提供了一个很好的起点，而无需事先具有 Docker 经验。

Sail 的核心是 docker-compose.yml 文件和存储在项目根目录的 sail 脚本。sail 脚本为 CLI 提供了便捷的方法，可用于与 docker-compose.yml 文件定义的 Docker 容器进行交互。

Webman Sail 支持 macOS、Linux 和 Windows (通过 [WSL2](https://docs.microsoft.com/en-us/windows/wsl/about)）。


## 安装 & 启动

1. 安装依赖

```shell
composer require roiwk/webman-sail --dev
```

2. webman命令执行 ```sail:install```。这个命令用于发布 ```docker-compose.yml```文件到你应用程序的根目录：  

```shell
php webman sail:install
```
或者直接指定需要安装的服务：  
```shell
php webman sail:install --with=mysql,redis
```

3. 启动服务。

```shell
./vendor/bin/sail up
```
访问： ```http://localhost``` 即可。
至此，基本的环境安装已经就绪，下来是配置和使用相关的文档：

## 环境变量
1. php版本默认使用8.1版本。 默认版本支持 7.4-8.2,更多可查看[定制容器](#定制容器); 具体版本按需设置```.env```文件中的配置即可。
```env
PHP_CLI_VERSION=8.1
```

2. 数据库配置  
```env
DB_PORT=3306
DB_DATABASE=test
DB_USERNAME=dev
DB_PASSWORD=123456
DB_ROOT_PASSWORD=password
```

## 执行命令

1. help，查看支持的指令帮助。
```shell
./vendor/bin/sail --help
```

2. sail === docker-compose  相当于docker-compose指令。
```shell
./vendor/bin/sail up -d
./vendor/bin/sail down
./vendor/bin/sail ps
```
3. sail === php-cli(container)  链接容器内部php指令。
```shell
./vendor/bin/sail php test.php
./vendor/bin/sail php -v
```
4. sail === composer(container) 链接容器内部composer指令。
```shell
./vendor/bin/sail composer update
./vendor/bin/sail composer require foo/bar
./vendor/bin/sail composer remove foo/bar
```
5. sail === mysql-cli  链接mysql/mariadb/psql容器内部指令。
```shell
./vendor/bin/sail mysql
```
6. sail === redis-cli  链接redis容器内部指令。
```shell
./vendor/bin/sail redis
```
7. sail === shell(container) 链接应用容器内部shell指令。
```shell
./vendor/bin/sail shell
./vendor/bin/sail root-shell               #root用户
```
8. sail === phpunit(container) 执行应用容器内部phpunit指令。
```shell
./vendor/bin/sail phpunit --bootstrap support/bootstrap.php
```

...等等功能，  
``` --help```期待你的发现与探索。


## 定制容器

因为 Sail 就是 Docker，所以你可以自由的定制任何内容，使用 ```sail:publish``` 命令可以将 Sail 预设的 Dockerfile 发布到你的应用程序中，以便于进行定制：

```shell
./vendor/bin/sail webman sail:publish
```

运行这个命令后，Sail 预设好的 Dockerfile 和其他配置文件将被生成发布到项目根目录的 docker 目录中。
完成上述操作后，可以按需修改Dockerfile中的php扩展等。修改完成后，执行以下命令重新构建容器即可：

```shell
./vendor/bin/sail build --no-cache
```

## 贡献

欢迎提交PR

## 鸣谢

灵感与借鉴：[laravel/sail](https://github.com/laravel/sail)


## 开源许可协议

 [MIT LICENSE](./LICENSE)