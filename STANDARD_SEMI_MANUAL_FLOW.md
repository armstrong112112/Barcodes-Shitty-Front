# ТЗ: основной Prepared / Regular flow генерации баркода

## 1. Коротко

Этот flow — нормально сделанный `prepared`, а не отдельный третий режим.

Пользователь:

1. выбирает страну, штат, ревизию и тип документа;
2. получает полную semantic-форму выбранного exact-профиля;
3. вручную заполняет базовые данные человека;
4. при желании нажимает `Random` или `Calculate` только возле разрешённого generated/calculated поля;
5. может исправить любой полученный результат;
6. нажимает финальную кнопку;
7. BFF проверяет полный semantic payload, внутри маппит его в AAMVA и вызывает render.

Главное отличие от Auto Flow:

- Auto сам выполняет всю последовательность действий;
- Prepared хранит те же короткие действия, но выполняет только одно действие после явного клика пользователя;
- результат одного действия может разблокировать следующую кнопку, но не запускает её;
- финальная кнопка Prepared не вызывает `random`, `calculate`, `prepare` или `ChainExecutor`.

```text
Auto:
base fields → BFF сам выполняет action 1 → action 2 → action 3 → render

Prepared:
полная semantic-форма
  → пользователь нажал Random у documentNumber
  → пользователь нажал Random у issueDate+expirationDate
  → пользователь нажал Calculate у inventoryNumber
  → пользователь всё проверил и отредактировал
  → BFF маппит draft в engine fields и делает render без скрытых actions
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
- `barcode_gen/src/barcode-config/helper/config.helper.ts:92-198` — в legacy metadata действительно объявлены random handlers для имени, DOB и адреса;
- `barcode_gen/src/barcode-config/barcode-config.service.ts` — поля, options, `randomHandler`, `calculateHandler` и builders ревизий;
- например, CA DL находится в `barcode-config.service.ts:42-192`: `DAQ` random, grouped dates, `DBA`, `DCK` и `DCF` calculate handlers записаны возле самих полей.

Наличие handler в legacy metadata не означает автоматическое включение его в новый публичный BFF-контракт. В принятом BFF flow базовые данные человека не генерируются: handlers имени, DOB и адреса остаются legacy-only. В Prepared публикуются только утверждённые действия для номера документа, дат выдачи/окончания и служебных идентификаторов exact-профиля.

### 2.3. BFF

В BFF уже есть части Auto Flow:

- `internal/usecase/chain_executor.go` — выполнение grouped random/calculate;
- `generate_usecase.go` — текущее разделение `auto` и `prepared`;
- `internal/domain/engine_fields.go` — semantic → AAMVA mapping;
- `internal/adapters/barcodegen/legacy/client.go` — public BarcodeGen adapter;
- `internal/adapters/barcodegen/legacy/internal_client.go` — grouped derive и internal render;
- `app/pages/generating.vue` — текущий frontend-вызов prepare/generate.

### 2.4. Что подтверждено текущим кодом, а что ещё целевое

На момент проверки реальный flow такой:

1. `GET /api/v1/revisions/:revision/schema` отдаёт static BFF schema с semantic names (`firstName`, `lastName`, `dateOfBirth`); query `mode=prepared` сейчас handler-ом не используется.
2. Frontend хранит значения по `field.name`, отправляет semantic base input в `prepare`, получает draft и затем отправляет его в `generate(mode="prepared")`.
3. `prepare` и Auto используют `ChainExecutor`; он сначала делает semantic → AAMVA normalization, поэтому возвращаемый `draftFields` фактически смешанный: в нём остаются public keys и появляются engine keys.
4. Текущий frontend работает именно потому, что финально отправляет этот mixed draft из `prepare`, а не чистый semantic prepared payload.
5. В `generate_usecase.go` `filterRenderValues` сейчас вызывается **до** `NormalizeEngineFields`. Поэтому прямой `generate(mode="prepared")` с чистыми semantic keys может потерять их на engine allowlist и не является надёжно реализованным путём.
6. `GenerateRequest.Fields` пока является свободным `map[string]any`. `NormalizeEngineFields` сохраняет уже присланные raw keys, поэтому текущая реализация не обеспечивает обещанное строгое `400` для каждого внешнего AAMVA key.
7. Raw-only payload тоже не является корректным публичным контрактом: `requiredInputFields` проверяются по semantic names. Например, один `DAC` не удовлетворяет обязательному `firstName`. Иными словами, фактический mixed payload — переходный implementation leak, а не контракт, который нужно закрепить.
8. `POST /api/v1/barcode/action`, Prepared schema с action metadata и per-action validation сейчас отсутствуют; это целевые изменения этого ТЗ.
9. В текущем `generate_usecase.go` условие записано как `mode != "prepared"`; его нужно заменить exact switch по разделу 8, чтобы неизвестный mode не запускал Auto.

Итоговая граница после реализации этого ТЗ:

- frontend и публичные BFF endpoints используют только semantic keys;
- AAMVA-коды разрешены только внутри BFF registry/adapters и на внутренней границе BFF → BarcodeGen;
- raw AAMVA key во внешнем `fields` или `values` отклоняется до Billing с `400 VALIDATION_ERROR`;
- Prepared переиспользует адаптеры и правила валидации, но один клик не запускает `ChainExecutor`: BFF выполняет ровно один разрешённый action exact-профиля.

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

Production BFF не получает форму из BarcodeGen на каждый пользовательский запрос. Полная semantic schema, mappings, options, constants и action registry хранятся в version-controlled exact-profile BFF config — это тот же источник, который уже использует текущий `GET /api/v1/revisions/:revision/schema`.

BarcodeGen metadata используется только при миграционном offline-аудите и compatibility tests:

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

`flag=false` важен для аудита: он возвращает `top + down[type]`. BFF переводит `abr` в semantic name при подготовке checked-in profile; raw `abr` не публикуется frontend как ключ формы.

### 4.1. Что BFF хранит в exact-profile config

Для каждого публичного поля:

- semantic `name`;
- человекочитаемый `label`;
- semantic `type` и UI `control`;
- `required`, `fallbackValue`;
- полный typed список `options`;
- validation rules;
- исходную группу и порядок;
- разрешённый короткий semantic action, если это generated/calculated field.

AAMVA mapping, server-owned `DAJ/DDB/QQQ` и точные downstream dependencies хранятся в той же конфигурации, но не выдаются клиенту как редактируемые поля.

```ts
type PreparedField = {
  name: string
  label: string
  type: "string" | "date" | "enum" | "number" | "boolean"
  control: "input" | "calendar" | "select" | "radio" | "toggle"
  required: boolean
  fallbackValue?: string | number | boolean
  options?: Array<{ label: string; value: string | number | boolean }>
  validation?: {
    minLength?: number
    maxLength?: number
    pattern?: string
  }
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

Ниже сокращён `fields`, чтобы показать несколько profile-specific actions. В реальном ответе присутствует каждое semantic field из `expectedFields`. Server-owned profile constants в `expectedFields` не входят.

```json
{
  "profileId": "US_CA_08292017",
  "mode": "prepared",
  "context": {
    "country": "USA",
    "state": "CA",
    "revision": "08292017",
    "documentType": "DL"
  },
  "expectedFields": [
    "firstName", "middleName", "lastName", "suffix", "dateOfBirth",
    "street", "city", "postalCode", "sex", "realId",
    "documentNumber", "issueDate", "expirationDate", "inventoryNumber",
    "documentDiscriminator", "vehicleClass", "restrictions", "endorsements",
    "heightInches", "weightPounds", "eyeColor", "hairColor",
    "organDonor", "veteran"
  ],
  "fields": [
    {
      "name": "documentNumber",
      "label": "Customer ID Number",
      "type": "string",
      "control": "input",
      "action": {
        "id": "random:documentNumber",
        "type": "random",
        "inputFields": [],
        "outputFields": ["documentNumber"]
      }
    },
    {
      "name": "issueDate",
      "label": "Issue Date",
      "type": "date",
      "control": "calendar",
      "action": {
        "id": "random:documentDates",
        "type": "random",
        "inputFields": ["dateOfBirth", "realId"],
        "outputFields": ["issueDate", "expirationDate"]
      }
    },
    {
      "name": "inventoryNumber",
      "label": "Inventory Control Number",
      "type": "string",
      "control": "input",
      "action": {
        "id": "calculate:inventoryNumber",
        "type": "calculate",
        "inputFields": ["issueDate", "documentNumber"],
        "outputFields": ["inventoryNumber"]
      }
    }
  ]
}
```

Это целевой публичный ответ BFF. Frontend не видит AAMVA dependencies, не разбирает исходный `randomHandler` и не отправляет произвольные inputs/outputs. Например, для California BFF сам добавляет внутренние `DAJ=CA`, `DDB=08292017`, `QQQ=DL`, затем маппит `issueDate → DBD` и `documentNumber → DAQ`.

### 4.3. Зачем хранить actions в BFF

Полная форма и разрешённые actions сохраняются в version-controlled exact-profile registry BFF.

Причины:

1. Auto и Prepared используют одни и те же проверенные внутренние dependencies.
2. Frontend не может подменить inputs или outputs.
3. Каждая кнопка имеет короткое и понятное semantic правило.
4. Compatibility tests сравнивают registry с `randomHandler/calculateHandler` BarcodeGen и ловят расхождение до релиза.
5. Не нужен runtime metadata call и отдельный длинный Prepared-chain.

Если compatibility fixture и сохранённый action не совпали, deployment/test должен завершиться ошибкой. Production BFF не угадывает правило по live metadata.

## 5. Состояние формы

Frontend хранит одну semantic-карту:

```ts
type PreparedValue = string | number | boolean

type PreparedFormState = {
  profileId: string
  expectedFields: string[]
  values: Record<string, PreparedValue>
}
```

Пример:

```json
{
  "firstName": "JOHN",
  "middleName": "",
  "lastName": "SMITH",
  "dateOfBirth": "07191994",
  "documentNumber": "",
  "issueDate": "",
  "expirationDate": "",
  "inventoryNumber": "",
  "documentDiscriminator": ""
}
```

`state`, revision и document type не дублируются в редактируемой карте: BFF восстанавливает их из `profileId` и отклоняет попытку подменить их через `fields`/`values`.

Правила:

- каждый `expectedField` должен быть известен exact-profile schema; обязательность задаётся отдельно через `required`;
- raw AAMVA keys (`DAC`, `DAQ`, `DCK`, `Z*` и другие) во внешней карте запрещены;
- `""`, `0` и `false` не удаляются как falsy;
- пользователь может заполнить action-поле вручную;
- successful action меняет только свои semantic `outputFields`;
- ошибка action не очищает старое значение;
- после action результат остаётся редактируемым;
- смена страны, штата, ревизии или типа создаёт новую форму;
- ответ старого запроса не применяется к новой форме.

## 6. Нажатие отдельной кнопки

Frontend отправляет только semantic `actionId` и текущие semantic значения. Списки inputs/outputs и server-owned context берутся из BFF registry, а не доверяются клиенту.

```http
POST /api/v1/barcode/action
Content-Type: application/json
```

```json
{
  "profileId": "US_CA_08292017",
  "actionId": "calculate:inventoryNumber",
  "values": {
    "issueDate": "04162023",
    "documentNumber": "A1234567"
  }
}
```

BFF:

1. находит exact profile по `profileId`;
2. находит сохранённый action по `actionId`;
3. отклоняет raw AAMVA и неизвестные semantic keys;
4. проверяет, что все semantic inputs присутствуют и валидны;
5. добавляет server-owned profile context и маппит разрешённые inputs в engine keys;
6. вызывает random или calculate adapter;
7. принимает только объявленные engine outputs;
8. маппит их обратно в semantic names и возвращает маленький patch формы.

### 6.1. Успешный ответ BFF

```json
{
  "success": true,
  "actionId": "calculate:inventoryNumber",
  "values": {
    "inventoryNumber": "23106A12345670401"
  }
}
```

Frontend делает:

```text
form.values.inventoryNumber = response.values.inventoryNumber
```

Он не заменяет весь draft ответом BFF.

### 6.2. Не хватает зависимостей

```json
{
  "success": false,
  "error": {
    "code": "ACTION_INPUT_MISSING",
    "message": "Заполните поля, необходимые для расчёта inventory number",
    "fields": [
      { "name": "issueDate", "label": "Issue Date" },
      { "name": "documentNumber", "label": "Customer ID Number" }
    ]
  }
}
```

HTTP: `422`.

### 6.3. Нельзя подменить action или прислать raw key

Неизвестный `actionId` возвращает `422 ACTION_NOT_ALLOWED`. Любой AAMVA key во внешнем `values`, например `DAQ`, возвращает `400 VALIDATION_ERROR` до вызова adapter-а.

### 6.4. Даты `issueDate + expirationDate`

`random:documentDates` — одно действие и один semantic-ответ:

```json
{
  "success": true,
  "actionId": "random:documentDates",
  "values": {
    "issueDate": "04162023",
    "expirationDate": "07192028"
  }
}
```

Внутри adapter работает с `DBD + DBA`, но эти ключи не пересекают публичную BFF-границу. BFF применяет правила `ISS_DBD_FLOW.md`:

- обе даты проверяются вместе;
- невалидная пара отбрасывается целиком;
- максимум 10 попыток;
- успешная пара возвращается атомарно;
- после десятой ошибки прежние значения формы сохраняются;
- Billing и render при action не вызываются.

### 6.5. Ещё два коротких примера

**Kentucky document number — кнопка недоступна без issue date:**

```json
{
  "profileId": "US_KY_08312018",
  "actionId": "random:documentNumber",
  "values": {
    "dateOfBirth": "07191994",
    "lastName": "SMITH",
    "issueDate": "04162023"
  }
}
```

```json
{
  "success": true,
  "actionId": "random:documentNumber",
  "values": { "documentNumber": "S12345678" }
}
```

BFF сам добавляет `DAJ=KY` из профиля и внутри маппит semantic inputs/outputs.

**Washington — два явных клика, а не автопродолжение:**

```text
клик 1: random:auditCode
клик 2: calculate:documentDiscriminator
```

После первого ответа BFF frontend только записывает `auditCode` и разблокирует вторую кнопку. Внутреннее правило registry при этом остаётся `[DAJ] → [DCJ]`, затем `[DAJ, DAQ, DCJ] → [DCF]`.

## 7. Короткие actions по всем ревизиям

Матрица ниже является **внутренней engine-матрицей BFF**, поэтому намеренно использует AAMVA-коды:

```text
engine field — random/calculate: [engine inputs] → [engine outputs]
```

Публичная schema и action API переводят её в semantic names. Основные соответствия action ID:

- `DAQ` → `random:documentNumber`;
- `DBD + DBA` → `random:documentDates`;
- отдельный `DBA` → `calculate:expirationDate`;
- `DCK` → `calculate:inventoryNumber`;
- `DCF` → `calculate:documentDiscriminator`;
- `DCJ` → `random:auditCode`.

Это не автоматическая цепочка. Каждая строка — отдельная кнопка. Порядок определяется только наличием semantic inputs после исключения server-owned context.

Базовые данные человека заполняются только вручную. Хотя `topFields()` BarcodeGen действительно объявляет legacy random handlers для `DAC + DAD + DCS`, `DBB` и `DAG + DAI + DAK`, BFF не включает их в Prepared registry и не отдаёт для них `field.action`. Это осознанное правило продукта, а не отсутствие capability в BarcodeGen.

Ниже перечислены только разрешённые profile-specific actions. Для grouped dates BFF хранит полный engine context `[DAJ, DBB, DDB, DDA]`; frontend передаёт только semantic `dateOfBirth` и `realId`, а `DAJ/DDB` BFF восстанавливает из exact profile. Для `DBA` отдельно сохранён его `calculateHandler`: обычно `[DAJ, DBB, DBD] → [DBA]`; исключения указаны прямо в профиле.

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
    "firstName": "JOHN",
    "middleName": "",
    "lastName": "SMITH",
    "suffix": "",
    "dateOfBirth": "07191994",
    "street": "123 MAIN ST",
    "city": "LOS ANGELES",
    "postalCode": "90001",
    "sex": 1,
    "realId": "F",
    "documentNumber": "A1234567",
    "issueDate": "04162023",
    "expirationDate": "07192028",
    "inventoryNumber": "23106A12345670401",
    "documentDiscriminator": "04/16/2023ABCDE/1234/28",
    "vehicleClass": "C",
    "restrictions": "NONE",
    "endorsements": "NONE",
    "heightInches": 70,
    "weightPounds": 180,
    "eyeColor": "BRO",
    "hairColor": "BRO",
    "organDonor": false,
    "veteran": false
  }
}
```

BFF в ветке `prepared`:

1. загружает immutable exact profile из BFF registry;
2. проверяет, что `fields` содержит только semantic names текущего профиля;
3. отклоняет raw AAMVA, server-owned и неизвестные поля;
4. проверяет обязательность, options, pattern, даты и profile-specific правила;
5. не запускает ни одного action;
6. маппит semantic draft в allowlisted engine fields;
7. сам подставляет неизменяемые `DAJ/DDB/QQQ` и profile constants;
8. только после полной validation вызывает Quote/Billing Block;
9. вызывает internal PDF417 render;
10. делает Capture при успехе или Release при ошибке.

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
    "message": "Заполните обязательные поля выбранной ревизии",
    "fields": [
      { "name": "inventoryNumber", "label": "Inventory Control Number" },
      { "name": "documentDiscriminator", "label": "Document Discriminator" }
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
    "message": "Поле vehicleClass содержит недопустимое значение",
    "fields": [
      {
        "name": "vehicleClass",
        "label": "Vehicle Class",
        "value": "X"
      }
    ]
  }
}
```

HTTP: `422`.

### 8.4. Raw AAMVA key во внешнем payload

Если клиент прислал, например, `"DAQ": "A1234567"` вместо `"documentNumber": "A1234567"`, BFF отвечает `400 VALIDATION_ERROR` с `details.unknownFields=["DAQ"]`. Запрос не доходит до Quote, Billing, helper или render.

## 9. Что изменить в BFF и frontend

### 9.1. BFF

1. Не добавлять `mode=standard`; использовать `mode=prepared`.
2. Расширить static exact-profile schema BFF до полной Prepared-формы; BarcodeGen `flag=false` использовать в offline compatibility tests, а не как runtime source of truth.
3. Зафиксировать один canonical semantic vocabulary (`documentNumber`, `postalCode`, `heightInches` и т. д.) и убрать публичные конкурирующие aliases (`idNumber`/`dlNumber`, `zipCode`, `height`).
4. Хранить semantic schema, внутренние AAMVA mappings и exact-profile registry коротких actions из раздела 7 в одном профиле.
5. Не включать legacy handlers имени, DOB и адреса в Prepared registry.
6. Отдавать semantic action рядом только с generated/calculated полем в schema response.
7. Добавить `POST /api/v1/barcode/action`.
8. В action request доверять только `profileId`, semantic `actionId` и semantic `values`; engine dependencies и context брать из registry.
9. До adapter-а отклонять raw AAMVA/server-owned/unknown keys с `400 VALIDATION_ERROR`.
10. Переиспользовать random/calculate adapters Auto Flow, но не запускать `ChainExecutor` для одного Prepared action.
11. В `generate_usecase.go` запускать executor только для exact `mode=auto`; неизвестный mode отклонять.
12. Для `mode=prepared` валидировать полный semantic payload и маппить его в engine keys **до** применения engine `renderAllowlist`.
13. После mapping строить внутренний allowlisted engine payload без повторной генерации; mixed draft больше не нужен и не возвращается клиенту.
14. Все ошибки Prepared validation возвращать до Quote/Billing.

### 9.2. Frontend

1. Рендерить все semantic поля Prepared schema.
2. Хранить значения в одной карте по semantic names; AAMVA-коды не использовать в состоянии формы и HTTP payload.
3. Показывать кнопку только при наличии `field.action`; базовые поля человека actions не имеют.
4. Disable кнопки, пока не заполнены её semantic inputs.
5. По клику отправлять один semantic `actionId`.
6. Применять только semantic `response.values` как patch.
7. Не запускать следующую кнопку автоматически.
8. Предупреждать перед заменой уже заполненных outputs.
9. Разрешать ручное редактирование после action.
10. Финально отправлять `mode=prepared` и полный semantic `fields` object.

## 10. Проверки

### 10.1. Contract tests BFF

Для каждого из 55 профилей:

- публичная schema содержит только canonical semantic field names;
- schema содержит правильные короткие semantic actions;
- внутренние engine inputs/outputs точно совпадают с разделом 7;
- неизвестный action отклоняется;
- raw AAMVA key в action/generate request отклоняется с `400 VALIDATION_ERROR` до внешних вызовов;
- server-owned `DAJ/DDB/QQQ` нельзя подменить через frontend payload;
- лишний semantic input не отправляется downstream;
- неожиданный engine output не применяется;
- actions имени, DOB и адреса отсутствуют во всех Prepared schemas;
- action не вызывает Billing или render;
- final Prepared Generate не вызывает random/calculate;
- `""`, `0` и `false` не теряются.

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
2. Prepared показывает полную semantic-форму exact revision.
3. Frontend хранит и отправляет только canonical semantic keys; AAMVA существует только внутри BFF/BarcodeGen.
4. Raw AAMVA или server-owned key во внешнем payload отклоняется до Billing.
5. Пользователь может заполнить всю форму без actions.
6. Имя, DOB и адрес не имеют actions и никогда не генерируются BFF.
7. Для каждого generated/calculated поля показывается только сохранённый exact-profile action.
8. Нажатие выполняет только один action.
9. Следующий action не запускается автоматически.
10. BFF не доверяет присланным клиентом dependencies.
11. Ответ action содержит только semantic patch `values`.
12. Ошибка action не очищает форму.
13. `issueDate + expirationDate` (`DBD + DBA` внутри) генерируются и применяются атомарно.
14. После action любое output-поле остаётся редактируемым.
15. Финальный `mode=prepared` не вызывает `prepare`, `ChainExecutor`, random или calculate.
16. Полнота и валидация проверяются до Quote/Billing.
17. Неизвестный `mode` не попадает в Auto Flow.
18. Все 55 профилей покрыты contract tests.
19. ON/AB не получают выдуманные US helper rules.
