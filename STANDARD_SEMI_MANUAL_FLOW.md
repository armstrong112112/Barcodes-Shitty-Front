# ТЗ: основной Prepared / Regular flow генерации баркода

## 1. Коротко

Этот flow — нормально сделанный `prepared`, а не отдельный третий режим.

Пользователь:

1. выбирает страну, штат, ревизию и тип документа;
2. получает полную форму выбранной ревизии;
3. заполняет все поля вручную;
4. при желании нажимает `Random` или `Calculate` возле отдельного поля;
5. может исправить любой полученный результат;
6. нажимает финальную кнопку;
7. BFF проверяет полный payload и вызывает render.

Главное отличие от Auto Flow:

- Auto сам выполняет всю последовательность действий;
- Prepared хранит те же короткие действия, но выполняет только одно действие после явного клика пользователя;
- результат одного действия может разблокировать следующую кнопку, но не запускает её;
- финальная кнопка Prepared не вызывает `random`, `calculate`, `prepare` или `ChainExecutor`.

```text
Auto:
base fields → BFF сам выполняет action 1 → action 2 → action 3 → render

Prepared:
полная форма
  → пользователь нажал Random у DAQ
  → пользователь нажал Random у DBD+DBA
  → пользователь нажал Calculate у DCK
  → пользователь всё проверил и отредактировал
  → render без скрытых actions
```

## 2. Откуда берётся логика

### 2.1. Проверенный legacy flow

Старый frontend уже работал как полуручной редактор:

- `barcodes/stateGet.php:454-637` читает `randomHandler` и `calculateHandler`, собирает зависимости и создаёт отдельные кнопки Generate;
- `barcodes/getRandom.php` передаёт текущие входы в старый random endpoint;
- `barcodes/getCalculate.php`, `getCalculate2.php`, `getCalculate3.php` делают то же для отдельных calculate-полей;
- `barcodes/genBar.php:76-88` хранит одну карту значений `$Msearch`;
- `barcodes/genBar.php:91-115` получает схему и находит поля `DBD`, `DAQ`, `DCF`, `DCK`;
- `barcodes/genBar.php:519-521` отправляет итоговую карту в render.

Полезная логика legacy:

```text
схема поля
  → список входов
  → список выходов
  → отдельная кнопка
  → ответ записывается в поля формы
```

Не переносим:

- ручную склейку JSON;
- строки вида `DAJ/CA,DBB/07191994`;
- поиск значений через DOM;
- PHP-прокси для каждого поля;
- старые URL и загрузку PNG на диск PHP-сервером.

### 2.2. BarcodeGen

Источники состава формы и действий:

- `barcode_gen/src/barcode/barcode.controller.ts` — schema, random, calculate и render routes;
- `barcode_gen/src/barcode/dto/get-data.dto.ts` — контекст формы;
- `barcode_gen/src/barcode-config/helper/present-fields.helper.ts` — полная форма `top + down[type]` при `flag=false`;
- `barcode_gen/src/barcode-config/helper/config.helper.ts:92-198` — общие actions имени, DOB и адреса в `topFields()`;
- `barcode_gen/src/barcode-config/barcode-config.service.ts` — поля, options, `randomHandler`, `calculateHandler` и builders ревизий;
- например, CA DL находится в `barcode-config.service.ts:42-192`: `DAQ` random, grouped dates, `DBA`, `DCK` и `DCF` calculate handlers записаны возле самих полей.

### 2.3. BFF

В BFF уже есть нужные части Auto Flow:

- `internal/usecase/chain_executor.go` — выполнение grouped random/calculate;
- `generate_usecase.go` — разделение `auto` и `prepared`;
- `internal/adapters/barcodegen/legacy/client.go` — public BarcodeGen adapter;
- `internal/adapters/barcodegen/legacy/internal_client.go` — grouped derive и internal render;
- `app/pages/generating.vue` — текущий frontend-вызов prepare/generate.

Prepared переиспользует адаптеры и правила валидации, но не `ChainExecutor`. Вместо длинной автоматической цепи BFF хранит набор коротких разрешённых actions выбранного профиля.

## 3. Два режима, а не три

| Этап | `auto` | `prepared` |
|---|---|---|
| Форма | сокращённая | полная форма ревизии |
| Служебные поля | обычно скрыты | видимы и редактируемы |
| Random/Calculate | BFF запускает сам | только после клика пользователя |
| Следующий action | запускается executor-ом | только разблокируется в UI |
| Ручное изменение результата | через draft | в любой момент в форме |
| Финальная кнопка | выполняет chain и render | только validation, Billing и render |

Отдельный `mode=standard` не нужен. В публичном контракте остаются:

```text
mode=auto
mode=prepared
```

`POST /api/v1/barcode/prepare` может оставаться частью Auto Flow. Новый ручной Prepared flow не обязан вызывать `prepare`: пользователь уже готовит полный draft в форме.

## 4. Получение полной Prepared-формы

Frontend обращается только к BFF:

```http
GET /api/v1/revisions/US_CA_08292017/schema?mode=prepared&country=USA&type=DL
```

BFF получает полную форму BarcodeGen:

```http
POST /api/v1/barcodes
Content-Type: application/json
```

```json
{
  "country": "USA",
  "DAJ": "CA",
  "DDB": "08292017",
  "type": "DL",
  "flag": false
}
```

`flag=false` важен: он возвращает `top + down[type]`. При `flag=true` общие поля имени, адреса и даты рождения могут потеряться.

### 4.1. Что BFF сохраняет без потерь

Для каждого поля:

- `abr` → `code`;
- `title` → `label`;
- `type`;
- `value` или `selected`;
- полный список `values`;
- `pattern`;
- исходную группу и порядок;
- разрешённый короткий action из exact-profile registry.

```ts
type PreparedField = {
  code: string
  label: string
  type: "input" | "calendar" | "select" | "choiser" | "radio" | "icon"
  initialValue?: string | number
  options?: Array<{ label: string; value: string | number }>
  pattern?: string
  position: number
  action?: PreparedAction
}

type PreparedAction = {
  id: string
  type: "random" | "calculate"
  inputFields: string[]
  outputFields: string[]
}
```

### 4.2. Пример ответа BFF на schema

Ниже сокращён `fields`, чтобы показать несколько profile-specific actions. В реальном ответе в `fields` присутствует каждый код из `expectedCodes`.

```json
{
  "profileId": "US_CA_08292017",
  "mode": "prepared",
  "context": {
    "country": "USA",
    "DAJ": "CA",
    "DDB": "08292017",
    "QQQ": "DL"
  },
  "expectedCodes": ["DAJ", "DDB", "QQQ", "DAC", "DAD", "DCS", "DCU", "DBB", "DAG", "DAI", "DAK", "DBC", "DDA", "DAQ", "DBD", "DBA", "DCK", "DCF", "DCA", "DCB", "DCD", "DAU", "DAW", "DAY", "DAZ", "DDK", "DDL"],
  "fields": [
    {
      "code": "DAQ",
      "label": "Customer ID Number",
      "type": "input",
      "action": {
        "id": "random:DAQ",
        "type": "random",
        "inputFields": ["DAJ"],
        "outputFields": ["DAQ"]
      }
    },
    {
      "code": "DBD",
      "label": "Issue Date",
      "type": "calendar",
      "action": {
        "id": "random:DBD+DBA",
        "type": "random",
        "inputFields": ["DAJ", "DBB", "DDB", "DDA"],
        "outputFields": ["DBD", "DBA"]
      }
    },
    {
      "code": "DCK",
      "label": "Inventory Control Number",
      "type": "input",
      "action": {
        "id": "calculate:DCK",
        "type": "calculate",
        "inputFields": ["DAJ", "DBD", "DAQ"],
        "outputFields": ["DCK"]
      }
    }
  ]
}
```

Это целевой ответ BFF. Frontend не разбирает исходный `randomHandler` сам и не отправляет произвольные зависимости.

### 4.3. Зачем хранить actions в BFF

Полная форма приходит из BarcodeGen, но разрешённые actions сохраняются в version-controlled exact-profile registry BFF.

Причины:

1. Auto и Prepared используют одни и те же проверенные зависимости.
2. Frontend не может подменить inputs или outputs.
3. Каждая кнопка имеет короткое и понятное правило.
4. Можно сравнивать registry с `randomHandler/calculateHandler` BarcodeGen и ловить расхождение до релиза.
5. Не нужен отдельный длинный Prepared-chain.

Если live schema BarcodeGen и сохранённый action не совпали, BFF не угадывает правило и не выполняет action. Он возвращает ошибку конфигурации.

## 5. Состояние формы

Frontend хранит одну карту:

```ts
type PreparedFormState = {
  profileId: string
  expectedCodes: string[]
  values: Record<string, string | number>
}
```

Пример:

```json
{
  "DAJ": "CA",
  "DDB": "08292017",
  "QQQ": "DL",
  "DAC": "JOHN",
  "DAD": "",
  "DCS": "SMITH",
  "DBB": "07191994",
  "DAQ": "",
  "DBD": "",
  "DBA": "",
  "DCK": "",
  "DCF": ""
}
```

Правила:

- каждый `expectedCode` должен существовать в карте;
- `""` и `0` не удаляются как falsy;
- пользователь может заполнить action-поле вручную;
- successful action меняет только свои `outputFields`;
- ошибка action не очищает старое значение;
- после action результат остаётся редактируемым;
- смена страны, штата, ревизии или типа создаёт новую форму;
- ответ старого запроса не применяется к новой форме.

## 6. Нажатие отдельной кнопки

Frontend отправляет только `actionId` и текущие значения. Списки inputs/outputs берутся из BFF registry, а не доверяются клиенту.

```http
POST /api/v1/barcode/action
Content-Type: application/json
```

```json
{
  "profileId": "US_CA_08292017",
  "documentType": "DL",
  "actionId": "calculate:DCK",
  "values": {
    "DAJ": "CA",
    "DBD": "04162023",
    "DAQ": "A1234567"
  }
}
```

BFF:

1. находит exact profile;
2. находит сохранённый action по `actionId`;
3. проверяет, что все его inputs присутствуют и валидны;
4. берёт только разрешённые inputs;
5. вызывает random или calculate adapter;
6. принимает только объявленные outputs;
7. возвращает маленький patch формы.

### 6.1. Успешный ответ BFF

```json
{
  "success": true,
  "actionId": "calculate:DCK",
  "values": {
    "DCK": "23106A12345670401"
  }
}
```

Frontend делает:

```text
form.values.DCK = response.values.DCK
```

Он не заменяет весь draft ответом BFF.

### 6.2. Не хватает зависимостей

```json
{
  "success": false,
  "error": {
    "code": "ACTION_INPUT_MISSING",
    "message": "Заполните поля, необходимые для расчёта DCK",
    "fields": [
      { "code": "DBD", "label": "Issue Date" },
      { "code": "DAQ", "label": "Customer ID Number" }
    ]
  }
}
```

HTTP: `422`.

### 6.3. Нельзя подменить action

Если клиент прислал неизвестный `actionId`:

```json
{
  "success": false,
  "error": {
    "code": "ACTION_NOT_ALLOWED",
    "message": "Это действие недоступно для выбранной ревизии"
  }
}
```

HTTP: `422`.

### 6.4. Даты `DBD + DBA`

`random:DBD+DBA` — одно действие и один ответ:

```json
{
  "success": true,
  "actionId": "random:DBD+DBA",
  "values": {
    "DBD": "04162023",
    "DBA": "07192028"
  }
}
```

BFF применяет правила `ISS_DBD_FLOW.md`:

- обе даты проверяются вместе;
- невалидная пара отбрасывается целиком;
- максимум 10 попыток;
- успешная пара возвращается атомарно;
- после десятой ошибки прежние значения формы сохраняются;
- Billing и render при action не вызываются.

### 6.5. Ещё три коротких примера

**Случайное имя — один клик меняет три поля:**

```json
{
  "profileId": "US_CA_08292017",
  "actionId": "random:DAC+DAD+DCS",
  "values": { "DBC": 1 }
}
```

```json
{
  "success": true,
  "actionId": "random:DAC+DAD+DCS",
  "values": { "DAC": "JOHN", "DAD": "MICHAEL", "DCS": "SMITH" }
}
```

**Kentucky DAQ — кнопка недоступна без DBD:**

```json
{
  "profileId": "US_KY_08312018",
  "actionId": "random:DAQ",
  "values": {
    "DAJ": "KY",
    "DBB": "07191994",
    "DCS": "SMITH",
    "DBD": "04162023"
  }
}
```

```json
{
  "success": true,
  "actionId": "random:DAQ",
  "values": { "DAQ": "S12345678" }
}
```

**Washington — два явных клика, а не автопродолжение:**

```text
клик 1: random:DCJ      [DAJ] → [DCJ]
клик 2: calculate:DCF   [DAJ, DAQ, DCJ] → [DCF]
```

После первого ответа BFF frontend только записывает `DCJ` и разблокирует вторую кнопку.

## 7. Короткие actions по всем ревизиям

Формат ниже:

```text
поле — random/calculate: [что взять] → [что вернуть]
```

Это не автоматическая цепочка. Каждая строка — отдельная кнопка. Порядок определяется только наличием inputs.

Базовые поля можно заполнить вручную. Кроме этого, `topFields()` BarcodeGen задаёт три общих legacy-actions для всех 55 профилей:

- `DAC + DAD + DCS` — random: `[DBC] → [DAC, DAD, DCS]`;
- `DBB` — random: `[] → [DBB]`;
- `DAG + DAI + DAK` — random: `[DAJ] → [DAG, DAI, DAK]`.

Они тоже запускаются только кнопкой и заменяют всю указанную группу атомарно. Физические данные и option-поля остаются ручными.

Ниже перечислены profile-specific actions. Для grouped dates BFF хранит полный проверяемый контекст `[DAJ, DBB, DDB, DDA]`; adapter передаёт BarcodeGen поддерживаемый handler input. Для `DBA` отдельно сохранён его `calculateHandler`: обычно `[DAJ, DBB, DBD] → [DBA]`; исключения указаны прямо в профиле.

### 1. California DL — `US_CA_08292017`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DBD, DAQ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD, DBA] → [DCF]`.

### 2. California ID — `US_CA_08292017_ID`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DBD, DAQ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD, DBA] → [DCF]`.

### 3. Utah 2021 — `US_UT_06012021`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD, DDB] → [DBA]`.
- `DCK` — calculate: `[DAJ, DDB, DAQ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.

### 4. Utah 2016 — `US_UT_04152016`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD, DDB] → [DBA]`.
- `DCK` — calculate: `[DAJ, DDB, DAQ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.

### 5. Michigan 2011 — `US_MI_Rev_01-21-2011`

- `DAQ` — random: `[DAJ, DAC, DAD, DCS, DBB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DAQ, DBB, DBA] → [DCK]`.
- Отдельного action для `DCF` нет.

### 6. Illinois 2015 — `US_IL_09172015`

- `DAQ` — random: `[DAJ, DAC, DAD, DCS, DBB, DBC] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DAQ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD] → [DCF]`.

### 7. Nevada 2021 — `US_NV_05242021`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DDB] → [DCF]`.

### 8. Nevada 2008 — `US_NV_10102008`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DDB] → [DCK]`.
- `DCF` — calculate: `[DAJ, DDB] → [DCF]`.

### 9. Georgia 2015 — `US_GA_07012015`

- `DAQ` — random: `[DAJ, DDB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DDB] → [DCK]`.
- `DCF` — calculate: `[DAJ, DDB, DCK] → [DCF]`.
- `county` пользователь выбирает вручную; adapter маппит его в `ZGD`.

### 10. Georgia 2019 — `US_GA_01022019`

- `DAQ` — random: `[DAJ, DDB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DDB] → [DCK]`.
- `DCF` — calculate: `[DAJ, DDB] → [DCF]`.
- Source self-edge `DCK → DCK` не используется; registry хранит исправленное правило без self-dependency.

### 11. North Carolina 2014 — `US_NC_10242014`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.

### 12. Massachusetts 2016 — `US_MA_02222016`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DBD, DAQ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD] → [DCF]`.

### 13. Minnesota 2017 — `US_MN_10232017`

- `DAQ` — random: `[DAJ, DAC, DAD, DCS, DBB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DAQ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.

### 14. Connecticut 2017 — `US_CT_02102017`

- `DAQ` — random: `[DAJ, DBB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DAQ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD] → [DCF]`.

### 15. Missouri 2020 — `US_MO_01272020`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DBD, DAQ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD] → [DCF]`.

### 16. Iowa 2017 — `US_IA_06292017`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DAQ, DBD] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.
- Повторяющийся `DBD` в source metadata дедуплицируется.

### 17. Montana 2015 — `US_MT_10072015`

- `DAQ` — random: `[DAJ, DBB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD] → [DCF]`.

### 18. Nebraska 2021 — `US_NE_04052021`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DAQ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DCK, DBD] → [DCF]`.

### 19. Rhode Island 2016 — `US_RI_01262016`

- `DAQ` — random: `[DAJ, DBB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.

### 20. Idaho 2023 — `US_ID_01312023`

- `DAQ` — random: `[DAJ, DBB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DBD] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.

### 21. West Virginia 2019 — `US_WV_06042019`

- `DAQ` — random: `[DAJ, DBB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.

### 22. Arizona 2014 — `US_AZ_02142014`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD, DDA] → [DBA]`.
- `DCK` — calculate: `[DAJ, DBD] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD, DCS, DAC, DBB] → [DCF]`.

### 23. Arizona 2023 — `US_AZ_03012023`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD, DDA] → [DBA]`.
- `DCK` — calculate: `[DAJ, DBD] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD, DCS, DAC, DBB] → [DCF]`.

### 24. Kansas 2017 — `US_KS_02242017`

- `DAQ` — random: `[DAJ, DAC, DCS, DBB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DDB] → [DCK]`.
- `DCF` — calculate: `[DAJ, DDB] → [DCF]`.

### 25. Pennsylvania 2016 — `US_PA_06072016`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD, DBB] → [DCF]`.

### 26. Pennsylvania 2022 — `US_PA_03252022`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD, DBB] → [DCF]`.

### 27. Arkansas 2018 — `US_AR_03012018`

- `DAQ` — random: `[DAJ, DAC, DCS, DBB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DDB] → [DCK]`.
- `DCF` — calculate: `[DAJ, DCS, DAC, DBB] → [DCF]`.

### 28. Florida 2019 — `US_FL_05012019`

- `DAQ` — random: `[DAJ, DAC, DAD, DCS, DBB, DBC] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.
- `safeDriver` — ручной toggle; это не random/calculate action.

### 29. Florida 2020 — `US_FL_03012020`

- `DAQ` — random: `[DAJ, DAC, DAD, DCS, DBB, DBC] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.
- `safeDriver` — ручной toggle; это не random/calculate action.

### 30. Mississippi 2016 — `US_MS_02222016`

- `DAQ` — random: `[DAJ, DBB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.

### 31. North Dakota 2014 — `US_ND_01082014`

- `DAQ` — random: `[DAJ, DAC, DCS, DBB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD] → [DCF]`.

### 32. Kentucky 2018 — `US_KY_08312018`

- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DAQ` — random: `[DAJ, DBB, DCS, DBD] → [DAQ]`.
- `DCK` — calculate: `[DAJ, DAQ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.
- Кнопка `DAQ` становится доступна после заполнения `DBD`; сама не запускается после dates action.

### 33. Tennessee 2011 — `US_TN_12022011`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.

### 34. Delaware 2017 — `US_DE_08022017`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DAQ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD] → [DCF]`.

### 35. Indiana 2018 — `US_IN_07242018`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD] → [DCF]`.

### 36. Washington 2019 — `US_WA_11122019`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCJ` — random: `[DAJ] → [DCJ]`.
- `DCF` — calculate: `[DAJ, DAQ, DCJ] → [DCF]`.
- Action для `DCK` отсутствует.

### 37. South Carolina 2018 — `US_SC_01012018`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.
- Action для `DCK` отсутствует.

### 38. New Jersey 2020 — `US_NJ_01082020`

- `DAQ` — random: `[DAJ, DAC, DAD, DCS, DBB, DBC, DAY] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD] → [DCF]`.

### 39. Alabama 2014 — `US_AL_11012014`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.

### 40. Colorado 2015 — `US_CO_10302015`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD, DDA] → [DBA]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.
- `DCJ` — random: `[DAJ] → [DCJ]`.
- Action для `DCK` отсутствует.

### 41. Maryland 2016 — `US_MD_06202016`

- `DAQ` — random: `[DAJ, DAD, DAC, DCS, DBB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.

### 42. Wisconsin 2015 — `US_WI_09012015`

- `DAQ` — random: `[DAJ, DAD, DAC, DCS, DBB, DBC] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD, DBA] → [DCF]`.

### 43. Oregon 2018 — `US_OR_07092018`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.

### 44. Louisiana 2015 — `US_LA_02102015`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.
- `DCJ` (`auditCode`) — только ручной input; random action отсутствует.

### 45. Texas 2016 — `US_TX_10102016`

- `DAQ` — random: `[DAJ, DDB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DDB, DAQ, DBD] → [DCK]`.
- `DCF` — calculate: `[DAJ, DDB] → [DCF]`.

### 46. Texas 2020 — `US_TX_02232020`

- `DAQ` — random: `[DAJ, DDB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DDB] → [DCK]`.
- `DCF` — calculate: `[DAJ, DDB, DBD] → [DCF]`.

### 47. Virginia 2008 — `US_VA_12102008`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DDB] → [DCK]`.
- `DCF` — calculate: `[DAJ, DDB] → [DCF]`.

### 48. Virginia 2023 — `US_VA_04222023`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DDB] → [DCK]`.
- `DCF` — calculate: `[DAJ, DDB] → [DCF]`.

### 49. Ohio 2018 — `US_OH_07012018`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.
- `DAX` не является кнопкой: internal derivation считает `Math.round(DAW * 0.45359237)`.

### 50. New Mexico 2016 — `US_NM_06162016`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DAQ] → [DCK]`.
- Action для `DCF` отсутствует: builder использует собственное фиксированное значение.

### 51. New York 2017 — `US_NY_09282017`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCF` — calculate: `[DAJ, DDB] → [DCF]`.
- Action для `DCK` отсутствует.

### 52. New York 2022 — `US_NY_03072022`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCF` — calculate: `[DAJ, DDB] → [DCF]`.
- Action для `DCK` отсутствует.

### 53. New Hampshire 2016 — `US_NH_06092016`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DCK] → [DCF]`.

### 54. Ontario 2017 — `CA_ON_08292017`

- Подтверждённых province-specific random/calculate actions нет.
- В source descriptor есть общие handlers, но BFF их не публикует, пока не подтверждены канадские форматы и правила.
- `DAQ`, `DBD`, `DBA`, `DCK`, `DCF` пользователь вводит вручную.
- BFF валидирует даты и канадские форматы, но не запускает чужие US actions.
- Рост переводится в сантиметры adapter-ом; отдельной кнопки для этого нет.

### 55. Alberta 2017 — `CA_AB_08292017`

- Подтверждённых province-specific random/calculate actions нет.
- В source descriptor есть общие handlers, но BFF их не публикует, пока не подтверждены канадские форматы и правила.
- `DAQ`, `DBD`, `DBA`, `DCF` пользователь вводит вручную.
- `DCK` профилю не нужен.
- BFF валидирует даты и канадские форматы, но не запускает чужие US actions.
- Рост и вес переводятся в профильные единицы adapter-ом; отдельных кнопок для этого нет.

## 8. Финальный Prepared Generate

Frontend отправляет полный снимок формы:

```http
POST /api/v1/barcode/generate
X-Idempotency-Key: <stable-key>
Content-Type: application/json
```

```json
{
  "revision": "US_CA_08292017",
  "barcodeType": "pdf417",
  "mode": "prepared",
  "units": 1,
  "confirmed": true,
  "fields": {
    "DAJ": "CA",
    "DDB": "08292017",
    "QQQ": "DL",
    "DAC": "JOHN",
    "DAD": "",
    "DCS": "SMITH",
    "DCU": "",
    "DBB": "07191994",
    "DAG": "123 MAIN ST",
    "DAI": "LOS ANGELES",
    "DAK": "90001",
    "DBC": 1,
    "DDA": "F",
    "DAQ": "A1234567",
    "DBD": "04162023",
    "DBA": "07192028",
    "DCK": "23106A12345670401",
    "DCF": "04/16/2023ABCDE/1234/28",
    "DCA": "C",
    "DCB": "NONE",
    "DCD": "NONE",
    "DAU": "70",
    "DAW": "180",
    "DAY": "BRO",
    "DAZ": "BRO",
    "DDK": 0,
    "DDL": 0
  }
}
```

BFF в ветке `prepared`:

1. загружает exact profile и актуальную полную форму;
2. проверяет контекст `DAJ/DDB/QQQ`;
3. проверяет наличие всех `expectedCodes`;
4. отклоняет неизвестные поля;
5. проверяет options, pattern, даты и profile-specific правила;
6. не запускает ни одного action;
7. только после validation вызывает Billing Block;
8. вызывает internal PDF417 render;
9. делает Capture при успехе или Release при ошибке.

Псевдокод целевого ветвления:

```go
switch req.Mode {
case "auto":
    values = chainExecutor.ExecuteGrouped(ctx, profile, req.Fields)
case "prepared":
    values = validateCompletePreparedFields(profile, req.Fields)
default:
    return ErrUnsupportedMode
}
```

Нельзя оставлять проверку вида `if mode != "prepared"`, потому что опечатка в mode попадёт в Auto Flow.

### 8.1. Успешный ответ BFF

```json
{
  "success": true,
  "barcodeUrl": "https://.../barcodes/7c7a.png",
  "format": "pdf417",
  "metadata": {
    "generationId": "7c7a...",
    "revision": "US_CA_08292017",
    "mode": "prepared"
  }
}
```

### 8.2. Неполная форма

```json
{
  "success": false,
  "error": {
    "code": "INCOMPLETE_PREPARED_PAYLOAD",
    "message": "Заполните все поля выбранной ревизии",
    "fields": [
      { "code": "DCK", "label": "Inventory Control Number" },
      { "code": "DCF", "label": "Document Discriminator" }
    ]
  }
}
```

HTTP: `422`. До Billing и BarcodeGen render запрос не доходит.

### 8.3. Невалидное значение

```json
{
  "success": false,
  "error": {
    "code": "PREPARED_FIELD_INVALID",
    "message": "Поле DCA содержит недопустимое значение",
    "fields": [
      {
        "code": "DCA",
        "label": "Vehicle Class",
        "value": "X"
      }
    ]
  }
}
```

HTTP: `422`.

## 9. Что изменить в BFF и frontend

### 9.1. BFF

1. Не добавлять `mode=standard`; использовать `mode=prepared`.
2. Для Prepared schema получать полную форму BarcodeGen с `flag=false`.
3. Хранить exact-profile registry коротких actions из раздела 7.
4. Отдавать action рядом с конкретным полем в schema response.
5. Добавить `POST /api/v1/barcode/action`.
6. В action request доверять только `profileId`, `actionId` и текущим values; dependencies брать из registry.
7. Переиспользовать random/calculate adapters Auto Flow, но не `ChainExecutor`.
8. В `generate_usecase.go` запускать executor только для exact `mode=auto`.
9. Для `mode=prepared` использовать полный пользовательский payload без повторной генерации.
10. Все ошибки Prepared validation возвращать до Billing.

### 9.2. Frontend

1. Рендерить все поля Prepared schema.
2. Хранить значения в одной карте по AAMVA-кодам.
3. Показывать кнопку только при наличии `field.action`.
4. Disable кнопки, пока не заполнены её inputs.
5. По клику отправлять один `actionId`.
6. Применять только `response.values` как patch.
7. Не запускать следующую кнопку автоматически.
8. Предупреждать перед заменой уже заполненных outputs.
9. Разрешать ручное редактирование после action.
10. Финально отправлять `mode=prepared` и полный fields object.

## 10. Проверки

### 10.1. Contract tests BFF

Для каждого из 55 профилей:

- schema содержит правильные короткие actions;
- action inputs и outputs точно совпадают с разделом 7;
- неизвестный action отклоняется;
- лишний input не отправляется downstream;
- неожиданный output не применяется;
- action не вызывает Billing или render;
- final Prepared Generate не вызывает random/calculate;
- пустые `""` и числовые `0` не теряются.

### 10.2. Особые fixtures

Обязательны отдельные тесты:

- California DL и ID как разные exact profiles;
- Michigan literal revision `Rev 01-21-2011`;
- Kentucky: `DAQ` зависит от `DBD`, но не запускается после dates action;
- Georgia 2019: self-edge `DCK → DCK` отсутствует;
- Iowa: повторный `DBD` дедуплицирован;
- WA/CO: отдельный random `DCJ`;
- LA: `DCJ` только ручной;
- Ohio: `DAX` выводится из `DAW` без UI-action;
- New Mexico: нет action `DCF`;
- NY/WA/SC/CO/AB: нет action `DCK`;
- ON/AB: нет подстановки US actions;
- даты возвращаются и применяются атомарно.

## 11. Критерии приёмки

1. В системе остаются только `auto` и `prepared`.
2. Prepared показывает полную форму exact revision.
3. Пользователь может заполнить всю форму без actions.
4. Для каждого generated/calculated поля показывается только сохранённый exact-profile action.
5. Нажатие выполняет только один action.
6. Следующий action не запускается автоматически.
7. BFF не доверяет присланным клиентом dependencies.
8. Ответ action содержит только patch `values`.
9. Ошибка action не очищает форму.
10. `DBD + DBA` генерируются и применяются атомарно.
11. После action любое output-поле остаётся редактируемым.
12. Финальный `mode=prepared` не вызывает `prepare`, `ChainExecutor`, random или calculate.
13. Полнота и валидация проверяются до Billing.
14. Все 55 профилей покрыты contract tests.
15. ON/AB не получают выдуманные US helper rules.
