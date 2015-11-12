#!/bin/bash
echo "START CLEAN VIRTUOSO"
SESSION=$(date +%Y-%m-%d-%H:%mi:%S)

if [ $# == 0 ]; then
    echo "OntoWiki root path is missing"
    exit 0
fi

if [ $# == 1 ]; then
    OWPATH=$1
fi

if [ ! -d "$OWPATH/dumps" ]; then
    mkdir $OWPATH/dumps
fi

mkdir $OWPATH/dumps/clean_$SESSION >/dev/null 2>&1
chmod -R 777 $OWPATH/dumps/clean_$SESSION >/dev/null 2>&1
curl -s -L "http://localhost:8890/sparql" --data-urlencode "default-graph-uri=" --data-urlencode "query=SELECT DISTINCT ?g WHERE { GRAPH ?g { ?s ?o ?p}}" --data-urlencode "format=text/csv" | sed "s/\"//g" | sed 1D | sort >$OWPATH/dumps/clean_$SESSION/graphs.lst

while read GRAPH
do
    GRAPH=$(echo $GRAPH | sed "s/\s*//g")

    if [ "$GRAPH" == "http://localhost/OntoWiki/Config/" ] || [ "$GRAPH" == "http://www.openlinksw.com/schemas/virtrdf#" ] || [ "$GRAPH" == "http://localhost:8890/DAV/" ] || [ "$GRAPH" == "http://localhost:8890/sparql" ] || [ "$GRAPH" == "http://www.w3.org/2002/07/owl#" ]; then
        echo "IGNORE $GRAPH";
    else
        echo "DELETE $GRAPH"
        DELETE=$DELETE"SPARQL DROP GRAPH <$GRAPH>;"
    fi

done < $OWPATH/dumps/clean_$SESSION/graphs.lst
IFS=' '  read -r ISQL_PROG virt_user virt_pw <<< "`exec $OWPATH/application/scripts/virtuoso.sh $OWPATH`"
eval "$ISQL_PROG -U $virt_user -P $virt_pw exec=\"$DELETE\"" > /dev/null
rm -rf $OWPATH/dumps/clean_$SESSION > /dev/null
echo "FINISHED CLEAN VIRTUOSO"
