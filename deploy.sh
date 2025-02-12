#!/bin/bash

# 停止应用
php bin/console messenger:stop-workers

# 拉取最新代码
git pull origin main

# 安装依赖
composer install --no-dev --optimize-autoloader

# 清除缓存
APP_ENV=prod php bin/console cache:clear

# 数据库迁移
php bin/console doctrine:migrations:migrate --no-interaction

# 重启消息队列
sudo supervisorctl restart messenger-consume:*

# 检查状态
sudo supervisorctl status 