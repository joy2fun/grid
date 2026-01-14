
Create migrations for the following tables. Keep column names short—ideally one word.
 - A table named stocks to store each stock’s code and a friendly name.
 - A table named day_prices to store daily prices for stocks. No timestamps are needed.
Also, create a Filament resource page for the stocks so I can manage them.

@qwen
---


Write a method to sync price by stock code via the following api:

```
https://web.ifzq.gtimg.cn/appstock/app/fqkline/get?param=sh601166,day,,,2000,qfq
```

`sh601166` is a sample stock code. The response is a json string. like: 
```json
{"code":0,"msg":"","data":{"sh601166":{"qfqday":[["2026-01-09","20.980","20.840","21.060","20.790","593631.000"],["2026-01-12","20.810","20.920","21.010","20.680","806680.000"],["2026-01-13","20.940","21.010","21.170","20.860","671490.000"],["2026-01-14","20.97","21.13","21.18","20.84","424393"]],"qt":{"sh601166":["1","\u5174\u4e1a\u94f6\u884c","601166","21.13","21.01","20.97","424393","249588","174806","21.12","163","21.11","633","21.10","922","21.09","381","21.08","446","21.13","990","21.14","654","21.15","97","21.16","263","21.17","1171","","20260114114705","0.12","0.57","21.18","20.84","21.13\/424393\/893063998","424393","89306","0.20","5.79","","21.18","20.84","1.62","4471.71","4471.71","0.57","23.11","18.91","1.22","-630","21.04","5.32","5.79","","","0.51","89306.3998","0.0000","0"," ","GP-A","0.33","-0.52","5.02","8.68","0.73","25.45","18.44","-1.35","2.13","4.29","21162855196","21162855196","-11.01","5.54","21162855196","","","13.91","-0.14","","CNY","0","___D__F__N","21.20","-9648"],"market":["2026-01-14 11:47:10|HK_open_\u4ea4\u6613\u4e2d|SH_close_\u5348\u95f4\u4f11\u5e02|SZ_close_\u5348\u95f4\u4f11\u5e02|US_close_\u5df2\u6536\u76d8|SQ_close_\u5348\u95f4\u4f11\u5e02|DS_close_\u5348\u95f4\u4f11\u5e02|ZS_close_\u5348\u95f4\u4f11\u5e02|NEWSH_close_\u5348\u95f4\u4f11\u5e02|NEWSZ_close_\u5348\u95f4\u4f11\u5e02|NEWHK_open_\u4ea4\u6613\u4e2d|NEWUS_close_\u5df2\u6536\u76d8|REPO_close_\u5348\u95f4\u4f11\u5e02|UK_close_\u672a\u5f00\u76d8|KCB_close_\u5348\u95f4\u4f11\u5e02|IT_close_\u672a\u5f00\u76d8|MY_open_\u4ea4\u6613\u4e2d|EU_close_\u672a\u5f00\u76d8|AH_open_\u4ea4\u6613\u4e2d|DE_close_\u672a\u5f00\u76d8|JW_open_\u4ea4\u6613\u4e2d|CYB_close_\u5348\u95f4\u4f11\u5e02|USA_close_\u5df2\u6536\u76d8|USB_close_\u5df2\u6536\u76d8|ZQ_close_\u5348\u95f4\u4f11\u5e02"]},"mx_price":{"mx":{"data":[],"timeline":[]},"price":{"data":[]}},"prec":"20.920","version":"18"}}}
```
The daily prices can access by `data.{code}`. each item is a array like: `["2026-01-09","20.980","20.840","21.060","20.790","593631.000"]`. represents: date, open_price, close_price, high_price, low_price, volume.

Make sure there is only one row for each code and date. Insert or update if exists. Performance is important.

Don't run any tests or add any test code. I'll test it myself.

It's not wise to write an API and add a route to achieve this. An artisan command is a better choice.

@qwen

---

add a button at the end of each row on the stocks list page.
show daily price as a chart when click the button.
use installed filament plugin leandrocfe-apex-charts, which uses apex charts under the hood.
display the chart as a candlestick chart.

@gemini


---
sync the realtime prices via http request 
```
https://qt.gtimg.cn/?q=sh601166,sz000001
```

the response is a piece of valid js code :
```js
v_sh601166="1~兴业银行~601166~20.60~21.01~20.97~1120913~488901~630936~20.59~388~20.58~3213~20.57~1750~20.56~1702~20.55~1660~20.60~60~20.61~296~20.62~78~20.63~130~20.64~61~~20260114143953~-0.41~-1.95~21.18~20.58~20.60/1120913/2343578275~1120913~234358~0.53~5.64~~21.18~20.58~2.86~4359.55~4359.55~0.56~23.11~18.91~1.76~8088~20.91~5.18~5.65~~~0.51~234357.8275~0.0000~0~ ~GP-A~-2.18~-3.01~5.15~8.68~0.73~25.45~18.44~-3.83~-0.43~1.68~21162855196~21162855196~86.61~2.90~21162855196~~~11.05~-0.15~~CNY~0~___D__F__N~20.50~3403"; v_sz000001="51~平安银行~000001~11.36~11.47~11.47~1153625~412435~740747~11.36~8223~11.35~20653~11.34~6993~11.33~10383~11.32~4393~11.37~14702~11.38~7459~11.39~10163~11.40~1974~11.41~2312~~20260114143954~-0.11~-0.96~11.47~11.36~11.36/1153625/1316771822~1153625~131677~0.59~5.11~~11.47~11.36~0.96~2204.48~2204.51~0.49~12.62~10.32~1.27~14035~11.41~4.31~4.95~~~0.55~131677.1822~0.0000~0~ ~GP-A~-0.44~-2.41~5.26~8.33~0.75~13.09~9.88~-1.73~-1.30~-0.53~19405600653~19405918198~16.09~9.00~19405600653~~~5.36~-0.09~~CNY~0~~11.30~10454";
```

each variable name is the stock code prefixed with `v_` like: v_{code}. so there are two stocks info in the response above: v_sh601166 and v_sz000001.
the value is a long string joined by `~`, you can split it into an array, the open price is at index 5. the high price is at index 33, low price at 34, and current/close price at 35(the part before the fisrt slash)

write a method to retrive the realtime prices info. something like:
```php
// return an array associated by the code
function getRealtimePrices(...$codes) {
    
}
```

@qwen
