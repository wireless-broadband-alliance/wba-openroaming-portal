#! /bin/bash
set -e

# Start Radsecproxy
if [ "$RUN_IN_BACKGROUND" = true ] ; then

    PID_FILE="/var/run/radsecproxy.pid"

    /sbin/radsecproxy -c /etc/radsecproxy.conf -i $PID_FILE

    trap 'echo SIGINT ; kill -INT $( cat $PID_FILE ) $SLEEP_PID ; exit' SIGINT
    trap 'echo SIGTERM ; kill -TERM $( cat $PID_FILE ) $SLEEP_PID ; exit' SIGTERM

    while true ; do
        sleep 3600 &
        SLEEP_PID=$!
        wait
    done

else

    /sbin/radsecproxy -f -c /etc/radsecproxy.conf -i /var/run/radsecproxy.pid -d 5

fi
