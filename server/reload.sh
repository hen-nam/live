echo "reloading ..."
pid=`pidof live_master`
kill -USR1 $pid
echo "reload successfully"
