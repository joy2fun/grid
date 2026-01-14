
Create migrations for the following tables. Keep column names short—ideally one word.
 - A table named stocks to store each stock’s code and a friendly name.
 - A table named day_prices to store daily prices for stocks. No timestamps are needed.
Also, create a Filament resource page for the stocks so I can manage them.

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
