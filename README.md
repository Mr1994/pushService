

#### API外网访问方式


高防：

#### Kafka配置

##### Topic

- Julive_Queue_Push_Service_APP2C

  > 这个Topic是针对居理C端APP的，量较大，因此单独拿出来，配置了6个分区

- Julive_Queue_Push_Service_APP2B

  > 这个Topic是针对居理B端APP的(咨询师APP、同舟APP、小猫头鹰宝典、ESA)，配置了6个分区

##### Group

> 两个Topic共用这些group，高级别对应的level是1，中级别对应的level是2、3，低级别对应的是4、5、6

- group_JULIVE_QUEUE_PUSH_SERVICE_low_priority
- group_JULIVE_QUEUE_PUSH_SERVICE_middle_priority
- group_JULIVE_QUEUE_PUSH_SERVICE_high_priority

#### 线上配置文件

> 以下文件如果有改动需要手动在152服务器上修改，然后再上线生效

```
config/prod/db.php
config/prod/params/push.php
config/prod/params/kafka.php
```

#### 脚本部署

> 脚本部署在```101.200.197.153(公)/172.18.173.254(私)```服务器上

```conf
    ;TOC消费推送消息脚本 高级别消息 
    [program:push_service_app2c_high_priority]
    command=/alidata/server/php/bin/php /alidata/www/push_service/yii push/send Julive_Queue_Push_Service_APP2C group_JULIVE_QUEUE_PUSH_SERVICE_high_priority
    log_stdout=true
    log_stderr=true
    stdout_logfile=/alidata/log/www/push_service/console/logs/push-service-app2c-high-priority.log
    stdout_logfile_maxbytes=100MB
    stdout_logfile_backups=10
    priority=1
    
    ;TOC消费推送消息脚本 中级别消息  
    [program:push_service_app2c_middle_priority]
    command=/alidata/server/php/bin/php /alidata/www/push_service/yii push/send Julive_Queue_Push_Service_APP2C group_JULIVE_QUEUE_PUSH_SERVICE_middle_priority
    log_stdout=true
    log_stderr=true
    stdout_logfile=/alidata/log/www/push_service/console/logs/push-service-app2c-middle-priority.log
    stdout_logfile_maxbytes=100MB
    stdout_logfile_backups=10
    priority=1
    
    ;TOC消费推送消息脚本 低级别消息  
    [program:push_service_app2c_low_priority]
    command=/alidata/server/php/bin/php /alidata/www/push_service/yii push/send Julive_Queue_Push_Service_APP2C group_JULIVE_QUEUE_PUSH_SERVICE_low_priority
    log_stdout=true
    log_stderr=true
    stdout_logfile=/alidata/log/www/push_service/console/logs/push-service-app2c-low-priority.log
    stdout_logfile_maxbytes=100MB
    stdout_logfile_backups=10
    priority=1
    
    ;TOB消费推送消息脚本 高级别消息  
    [program:push_service_app2b_high_priority]
    command=/alidata/server/php/bin/php /alidata/www/push_service/yii push/send Julive_Queue_Push_Service_APP2B group_JULIVE_QUEUE_PUSH_SERVICE_high_priority
    log_stdout=true
    log_stderr=true
    stdout_logfile=/alidata/log/www/push_service/console/logs/push-service-app2b-high-priority.log
    stdout_logfile_maxbytes=100MB
    stdout_logfile_backups=10
    priority=1
    
    ;TOB消费推送消息脚本 中级别消息  
    [program:push_service_app2b_middle_priority]
    command=/alidata/server/php/bin/php /alidata/www/push_service/yii push/send Julive_Queue_Push_Service_APP2B group_JULIVE_QUEUE_PUSH_SERVICE_middle_priority
    log_stdout=true
    log_stderr=true
    stdout_logfile=/alidata/log/www/push_service/console/logs/push-service-app2b-middle-priority.log
    stdout_logfile_maxbytes=100MB
    stdout_logfile_backups=10
    priority=1
    
    ;TOB消费推送消息脚本 低级别消息 
    [program:push_service_app2b_low_priority]
    command=/alidata/server/php/bin/php /alidata/www/push_service/yii push/send Julive_Queue_Push_Service_APP2B group_JULIVE_QUEUE_PUSH_SERVICE_low_priority
    log_stdout=true
    log_stderr=true
    stdout_logfile=/alidata/log/www/push_service/console/logs/push-service-app2b-low-priority.log
    stdout_logfile_maxbytes=100MB
    stdout_logfile_backups=10
    priority=1

    ;TOB消费推送消息脚本 低级别消息  
    [program:push_service_app2b_clow_priority]
    command=/alidata/server/php/bin/php /alidata/www/push_service/yii push/send Julive_Queue_Push_Service_APP2B group_JULIVE_QUEUE_PUSH_SERVICE_clow_priority
    log_stdout=true
    log_stderr=true
    stdout_logfile=/alidata/log/www/push_service/console/logs/push-service-app2b-clow-priority.log
    stdout_logfile_maxbytes=100MB
    stdout_logfile_backups=10
    priority=1
    
    
    ;定时推送消息脚本 
    [program:push_service_tick_send]
    command=/alidata/server/php/bin/php /alidata/www/push_service/yii push/tick-send
    log_stdout=true
    log_stderr=true
    stdout_logfile=/alidata/log/www/push_service/console/logs/push-service-tick-send.log
    stdout_logfile_maxbytes=100MB
    stdout_logfile_backups=10
    priority=1
```

