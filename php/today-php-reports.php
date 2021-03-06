<?php
  require("dnm-condb-php.php");
  
  if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $date = $_POST['todayDate'];
    // Set default date to SQL Wildcard
    /*$year = (!empty($date[0]) ? $date[0] : "%");
    $month = (!empty($date[1]) ? $date[1] : "%");
    $day = (!empty($date[2]) ? $date[2] : "%");*/
  } else {
    //$year = $month = $day = "%";
    echo "Error occured!";
  }

  // Retrieved date value
  //$transaction_type = "EXPENSE";
  //$year = "2016";

  // Send query and fetch data from database
  function fetch($query_str, $array_format = null) {
    if($array_format !== MYSQLI_ASSOC
        && $array_format !== MYSQLI_NUM
        && $array_format !== MYSQLI_BOTH
      ) {$array_format = null;}
    global $con;
    $query = mysqli_query($con, $query_str) or die ("Error: could not send query, " . mysqli_error($con));
    if(is_null($array_format)) {
      $array_format = MYSQLI_NUM;
    } else {
      $array_format = MYSQLI_ASSOC;
    }
    $rows = [];
    while($row = mysqli_fetch_array($query, $array_format)) {
      $rows[] = $row;
    }
    //print_r($rows);
    return $rows;
  }
  // Show numeric data array
  function data_table($show_type, $thead, $col_head_arr, $data_arr) {
    // Validate arguments
    if(gettype($thead) !== 'string'
       || gettype($col_head_arr) !== 'array'
       || gettype($data_arr) !== "array"
    ) {return false;}
    $colhead = $col_head_arr;
    $table = "<table class='today-reports-table'><thead><td colspan='"
    . count($colhead) . "'>{$thead}</td></thead><tbody><tr>";
    // Set column header of table
    for($i = 0; $i < count($colhead); $i++) {
      $table .= "<td>{$colhead[$i]}</td>";
    }
    $total_amount = 0;
    if($show_type === "num") {
      for($i = 0; $i < count($data_arr); $i++) {
        $table .= "</tr><tr>";
        for($j = 0; $j < count($data_arr[$i]); $j++) {
          $value = $data_arr[$i][$j];
          if(count($data_arr[$i]) - $j === 1) {
            $total_amount += $value;
            $value = number_format($value, 2);
          } else {
            $value = ucfirst($value);
          }
          $table .= "<td>{$value}</td>";
        }
        $table .= "</tr>";
      }
    } else if($show_type === "assoc") {
      for($i = 0; $i < count($data_arr); $i++) {
        foreach ($data_arr[$i] as $k => $v) {
          $key = ucfirst($k);
          $value = number_format($v, 2);
          $total_amount += $v;
          $table .= "<tr><td>{$key}</td><td>{$value}</td></tr>";
        }
      }
    } else {return false;}
    $table .= "<tr><td>Total</td><td colspan='" . (count($colhead) - 1)
            . "'>" . number_format($total_amount, 2) . "</td></tr>";
    $table .= "</tbody></table>";
    return $table;
  }

  // Fetch total amount based on category
  function totalAmount_category($transaction_type) {
    if($transaction_type !== "in"
        && $transaction_type !== "ex"
      ) {return false;}
    $tr_type = $transaction_type;
    global $date;
    $query = "SELECT DISTINCT {$tr_type}_categories.{$tr_type}_cats AS category, 
                (
                  SELECT SUM(record.amount)
                  FROM record
                  WHERE transaction_type = '{$tr_type}'
                  AND categories = {$tr_type}_categories.{$tr_type}_cats
                  AND date LIKE '{$date}'
                ) AS Amount
              FROM {$tr_type}_categories
              INNER JOIN record
              ON {$tr_type}_categories.{$tr_type}_cats = record.categories
              ORDER BY Amount DESC;";
    return fetch($query);
  }
  // Fetch total amount based on necessity
  function totalAmount_necessity() {
    global $date;
    $query = "SELECT 
              (
                  SELECT SUM(amount)
                  FROM record
                  WHERE transaction_type = 'ex'
                  AND necessity = 0
                  AND date LIKE '{$date}'
              ) AS unneccessary,
              (
                  SELECT SUM(amount)
                  FROM record
                  WHERE transaction_type = 'ex'
                  AND necessity = 1
                  AND date LIKE '{$date}'
              ) AS necessary;";
    //echo $query;
    return fetch($query, MYSQLI_ASSOC);
  }
  // Fetch total amount based on income type
  function totalAmount_incomeType() {
    global $date;
    $query = "SELECT 
              (
                  SELECT SUM(amount)
                  FROM record
                  WHERE transaction_type = 'in'
                  AND in_type = 'act'
                  AND date LIKE '{$date}'
              ) AS active,
              (
                  SELECT SUM(amount)
                  FROM record
                  WHERE transaction_type = 'in'
                  AND in_type = 'pas'
                  AND date LIKE '{$date}'
              ) AS passive;";
    return fetch($query, MYSQLI_ASSOC);
  }
  function totalAmount_person($person) {
    if($person !== 'payer'
        && $person !== 'payee')
      {return false;}
    $tr_type = ($person === 'payer' ? 'in' : 'ex');
    global $date;
    $query = "SELECT DISTINCT p.{$person} AS person,
              (
                  SELECT SUM(amount)
                  FROM record a
                  WHERE a.{$person} = p.{$person}
                  AND transaction_type = '{$tr_type}'
                  AND date LIKE '{$date}'
              ) AS total_amount
              FROM record p
              INNER JOIN record a
              ON p.{$person} = a.{$person}
              ORDER BY total_amount DESC
              LIMIT 10;";
    return fetch($query);
  }
  function totalAmount_subcat($transaction_type) {
    if($transaction_type !== "in"
        && $transaction_type !== "ex"
    ) {return false;}
    $tr_type = $transaction_type;
    global $date;
    $query = "SELECT DISTINCT
                a.{$tr_type}_subcats AS Sub_category,
                b.{$tr_type}_cats AS Category,
                (
                  SELECT SUM(amount)
                  FROM record
                  WHERE transaction_type = '{$tr_type}'
                  AND subcategories = Sub_category
                  AND categories = Category
                  AND date LIKE '{$date}'
                ) AS Amount
              FROM {$tr_type}_categories a
              INNER JOIN {$tr_type}_categories b
              ON a.{$tr_type}_subcats = b.{$tr_type}_subcats
              ORDER BY Amount DESC
              LIMIT 10;";
    return fetch($query);
  }
  // Fetch total amount based on account
  function totalAmount_account() {
    global $date;
    $query = "SELECT DISTINCT
                account AS Account,
                (
                  (
                    (
                      SELECT  IFNULL(SUM(amount), 0)
                      FROM    record
                      WHERE   acc = Account
                        AND   transaction_type = 'in'
                        AND   date BETWEEN
                            (
                              SELECT MIN(date)
                              FROM record
                            ) AND '{$date}'
                    )
                    +
                    (
                      SELECT  IFNULL(SUM(amount), 0)
                      FROM    record
                      WHERE   to_acc = Account
                        AND   transaction_type = 'tr'
                        AND   date BETWEEN
                            (
                              SELECT MIN(date)
                              FROM record
                            ) AND '{$date}'
                    )
                  )
                  -
                  (
                    (
                      SELECT  IFNULL(SUM(amount), 0)
                      FROM    record
                      WHERE   acc = Account
                        AND   transaction_type = 'ex'
                        AND   date BETWEEN
                            (
                              SELECT MIN(date)
                              FROM record
                              ) AND '{$date}'
                    )
                    +
                    (
                      SELECT  IFNULL(SUM(amount), 0)
                      FROM    record
                      WHERE   from_acc = Account
                        AND   transaction_type = 'tr'
                        AND   date BETWEEN
                            (
                              SELECT MIN(date)
                              FROM record
                            ) AND '{$date}'
                    )
                  )
                ) AS Remain
              FROM account;";
    return fetch($query);
  }
  // Fetch total amount based on sub account
  function totalAmount_subaccount() {
    global $date;
    $query = "SELECT DISTINCT
                s.sub_account AS Sub_account,
                a.account AS Account,
                (
                  (
                    (
                      SELECT  IFNULL(SUM(amount), 0)
                      FROM    record
                      WHERE   subacc = s.sub_account
                        AND   transaction_type = 'in'
                        AND   date BETWEEN
                            (
                              SELECT MIN(date)
                              FROM record
                            ) AND '{$date}'
                    )
                    +
                    (
                      SELECT  IFNULL(SUM(amount), 0)
                      FROM    record
                      WHERE   to_subacc = s.sub_account
                        AND   transaction_type = 'tr'
                        AND   date BETWEEN
                            (
                              SELECT MIN(date)
                              FROM record
                            ) AND '{$date}'
                    )
                )
                -
                (
                    (
                      SELECT  IFNULL(SUM(amount), 0)
                      FROM    record
                      WHERE   subacc = s.sub_account
                        AND   transaction_type = 'ex'
                        AND   date BETWEEN
                            (
                              SELECT MIN(date)
                              FROM record
                            ) AND '{$date}'
                    )
                    +
                    (
                      SELECT  IFNULL(SUM(amount), 0)
                      FROM    record
                      WHERE   from_subacc = s.sub_account
                        AND   transaction_type = 'tr'
                        AND   date BETWEEN
                            (
                              SELECT MIN(date)
                              FROM record
                            ) AND '{$date}'
                    )
                  )
                ) AS Remain
              FROM account s
              INNER JOIN account a
              ON s.account = a.account;";
    return fetch($query);
  }
  // Get number of transactions of the day
  function transact_num() {
    global $date;
    $query = "SELECT DISTINCT
                (
                  SELECT COUNT(*)
                  FROM record
                  WHERE transaction_type = 'ex'
                  AND date = '{$date}'
                ) AS expense,
                (
                  SELECT COUNT(*)
                  FROM record
                  WHERE transaction_type = 'in'
                  AND date = '{$date}'
                ) AS income
              FROM record
              WHERE date = '{$date}';";
    return fetch($query);
  }


  //
  //
  //  ----- GET TOTAL_AMOUNT FROM SUM() INSTEAD OF PHP LOOP -----
  //  ----- WHY FETCHING DATA VIA totalAmount_person('payer') IS VERY SLOW -----
  //
  //

  /*$acc = data_table("num", "Account", ["Account", "Remain"], totalAmount_account())
  . data_table("num", "Subaccount", ["Subaccount", "Account", "Remain"], totalAmount_subaccount());
  $gen = data_table("num", "Expense", ["Category", "Amount"], totalAmount_category("ex"))
  . data_table("assoc", "Necessity", ["Necessity", "Amount"], totalAmount_necessity())
  . data_table("num", "Subcategory", ["Subcategory", "Category", "Amount"], totalAmount_subcat('ex'))
  . data_table("num", "Payee", ["Category", "Amount"], totalAmount_person('payee'))
  . data_table("num", "Income", ["Category", "Amount"], totalAmount_category("in"))
  . data_table("assoc", "Income type", ["Income type", "Amount"], totalAmount_incomeType())
  . data_table("num", "Subcategory", ["Subcategory", "Category", "Amount"], totalAmount_subcat('in'));
  */
  //. data_table("num", "Payer", ["Category", "Amount"], totalAmount_person('payer'));

  // Manage transact_num()
  $tnum = transact_num();
  if(!empty($tnum)) {
    $_transex = $tnum[0][0];
    $_transin = $tnum[0][1];
  } else {
    $_transex = 0;
    $_transin = 0;
  }
  //print_r(transact_num());
  /*$deep = " <p>{$tran_in} income transaction(s)"
    . "<br>{$tran_ex} expense transaction(s)"
    . "<br>Total " . ($tran_ex + $tran_in) . " transaction(s).</p>";

  $json = array("acc" => $acc, "gen" => $gen, "deep" => $deep);
  */

  // --- TEST DATA SHEET ---
  $_acc = data_table("num", "Account", ["Account", "Remain"], totalAmount_account())
  . data_table("num", "Subaccount", ["Subaccount", "Account", "Remain"], totalAmount_subaccount());
  // expense
  $_necessity = data_table("assoc", "Necessity", ["Necessity", "Amount"], totalAmount_necessity());
  $_excat = data_table("num", "Expense", ["Category", "Amount"], totalAmount_category("ex"));
  $_exsubcat = data_table("num", "Subcategory", ["Subcategory", "Category", "Amount"], totalAmount_subcat('ex'));
  $_payee = data_table("num", "Payee", ["Payee", "Amount"], totalAmount_person('payee'));
  // income
  $_incometype = data_table("assoc", "Income type", ["Income type", "Amount"], totalAmount_incomeType());
  $_incat = data_table("num", "Income", ["Category", "Amount"], totalAmount_category("in"));
  $_insubcat = data_table("num", "Subcategory", ["Subcategory", "Category", "Amount"], totalAmount_subcat('in'));
  $_payer = data_table("num", "Payer", ["Category", "Amount"], totalAmount_person('payer'));
  // amount and transact
  $_amountex = null;
  $_amountin = null;
  /*$jsonObj = {
    'necessity': $_necessity,
    'excat': $_excat,
    'exsubcat': $_exsubcat,
    'payee': $_payee
  };
  */
  $jsonArrNum = [
    "acc" => $_acc,
    "ex" => [$_necessity, $_excat, $_exsubcat, $_payee],
    "in" => [$_incometype, $_incat, $_insubcat, $_payer],
    "totalAmount" => [$_amountex, $_amountin],
    "transact" => [$_transex, $_transin]
  ];

  echo json_encode($jsonArrNum);

  mysqli_close($con);
?>