#!/bin/bash

echo "START VIRTUOSO GRAPH EXPORT"

a=0
SESSION=$(date +%Y-%m-%d-%H:%m:%S)

if [ $# == 0 ]; then
    echo "OntoWiki root path is missing"
    exit 0
fi

if [ $# == 1 ]; then
    OWPATH=$1
fi

if [ ! -d "$OWPATH/dumps" ]; then
    mkdir $OWPATH/dumps >/dev/null 2>&1
fi

mkdir -p /tmp/export_$SESSION
mkdir $OWPATH/dumps/export_$SESSION >/dev/null 2>&1
chmod -R 777 $OWPATH/dumps/export_$SESSION >/dev/null 2>&1

IFS=' '  read -r ISQL_PROG virt_user virt_pw <<< "`exec $OWPATH/application/scripts/virtuoso.sh $OWPATH`"

echo "LOAD ${OWPATH}/application/scripts/dump_graph.sql;" | $ISQL_PROG -U $virt_user -P $virt_pw > /dev/null 2>&1

curl -s -L "http://localhost:8890/sparql" --data-urlencode "default-graph-uri=" --data-urlencode "query=SELECT DISTINCT ?g WHERE { GRAPH ?g { ?s ?o ?p}}" --data-urlencode "format=text/csv" | sed "s/\"//g" | sed 1D | sort >$OWPATH/dumps/export_$SESSION/graphs.lst

while read GRAPH
do
    GRAPH=$(echo $GRAPH | sed "s/\s*//g")

    if [ "$GRAPH" == "http://www.openlinksw.com/schemas/virtrdf#" ] || [ "$GRAPH" == "http://localhost:8890/DAV/" ] || [ "$GRAPH" == "http://localhost:8890/sparql" ] || [ "$GRAPH" == "http://www.w3.org/2002/07/owl#" ]; then
        echo "IGNORE $GRAPH";
    else
        COUNT=$(curl -s -L "http://localhost:8890/sparql" --data-urlencode "default-graph-uri=" --data-urlencode "query=SELECT (COUNT(*) AS ?no) {GRAPH <$GRAPH> {?s ?p ?o }}" --data-urlencode "should-sponge=" --data-urlencode "format=text/csv" | sed "s/\"//g" | sed 1D | sed 's/[^0-9]*//g')
        a=$(($a+1))
        echo "EXPORT $GRAPH (containing $COUNT triples) to dumps/export_$SESSION"

        # now dump graph
        echo "dump_one_graph('${GRAPH}', '/tmp/export_$SESSION/${a}_', 1000000000);" | $ISQL_PROG -U $virt_user -P $virt_pw >/dev/null
    fi
done < $OWPATH/dumps/export_$SESSION/graphs.lst

echo "FINISHED VIRTUOSO GRAPH EXPORT"
echo "ALL DATA SAVED IN ./dumps/export_$SESSION/"

cp -a /tmp/export_$SESSION/* "${OWPATH}/dumps/export_$SESSION"
rm -rf "/tmp/export_$SESSION"