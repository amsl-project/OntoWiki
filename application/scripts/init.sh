#!/bin/bash
SESSION=$(date +%Y-%m-%d-%H:%m:%S)
echo "START DATA INITIALISATION"

if [ $# == 0 ]; then
    echo "OntoWiki root path is missing"
    exit 0
fi

if [ $# == 1 ]; then
    OWPATH=$1
fi

mkdir /tmp/init_$SESSION >/dev/null 2>&1
chmod -R 777 /tmp/init_$SESSION/* >/dev/null 2>&1
cp $OWPATH/sample-data/*ttl* /tmp/init_$SESSION >/dev/null 2>&1
IFS=' '  read -r ISQL_PROG virt_user virt_pw <<< "`exec $OWPATH/application/scripts/virtuoso.sh $OWPATH`"
echo "delete from DB.DBA.load_list;" | $ISQL_PROG -U $virt_user -P $virt_pw >/dev/null 2>&1
echo "CREATE PROCEDURE create_silent_graphs () {
    ld_dir('/tmp/init_$SESSION', '*.ttl*', null);
    rdf_loader_run();
    log_message(sprintf('Creating silent graphs: '));
    FOR (SELECT * FROM DB.DBA.load_list AS sub WHERE ll_state=2 OPTION (LOOP)) DO {
        log_message (sprintf ( 'Executing: SPARQL CREATE SILENT GRAPH <%s>', ll_graph)) ;
        exec (sprintf ( 'SPARQL CREATE SILENT GRAPH <%s>', ll_graph)) ;
    }
} ; " > /tmp/init_create_silent_graphs.sql
echo "LOAD /tmp/init_create_silent_graphs.sql ; " | $ISQL_PROG -U $virt_user -P $virt_pw >/dev/null 2>&1
echo "create_silent_graphs () ;" | $ISQL_PROG -U $virt_user -P $virt_pw >/dev/null 2>&1
echo "delete from DB.DBA.load_list;" | $ISQL_PROG -U $virt_user -P $virt_pw >/dev/null 2>&1
rm -rf /tmp/init_* >/dev/null 2>&1
echo "FINISHED DATA INITIALISATION"
