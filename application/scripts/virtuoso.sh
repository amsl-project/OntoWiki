#!/bin/bash
if [ $# == 0 ]; then
    echo "OntoWiki root path is missing"
    exit 0
fi
if [ $# == 1 ]; then
    OWPATH=$1
fi
if [ -e "/usr/bin/isql-vt" ]
then
  ISQL_PROG="isql-vt"
else
  if [ -e "/usr/bin/isql" ]
  then
    ISQL_PROG="isql"
  else
    ISQL_PROG=$(which isql)
    if [ -z $ISQL_PROG ]
      then exit 0
    fi
  fi
fi
virt_user=$(sed -r -n 's/.*store.virtuoso.username *= *"*([^"]*)"*/\1/p' < $OWPATH/config.ini)
virt_pw=$(sed -r -n 's/.*store.virtuoso.password *= *"*([^"]*)"*/\1/p' < $OWPATH/config.ini)
echo "$ISQL_PROG $virt_user $virt_pw"
