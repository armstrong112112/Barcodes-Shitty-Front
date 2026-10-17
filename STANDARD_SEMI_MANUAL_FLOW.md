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

### 2.1. Что именно делал старый PHP frontend

Старый PHP frontend — только **поведенческий референс**. Новый frontend не должен открывать его страницы, вызывать его PHP-файлы или повторять его внутренний формат запросов.

Нужный нам сценарий находился в ветке `Regular` файла `barcodes/stateGet.php` и работал так.

#### Шаг 1. Пользователь выбирал exact-профиль

До показа формы пользователь выбирал страну, штат, дату ревизии и тип документа. PHP сохранял эти значения в скрытые поля:

- `DAJ` — штат;
- `DDB` — дата ревизии;
- `QQQ` — тип документа;
- `DCG` — страна.

После этого `stateGet.php` запрашивал у старого BarcodeGen описание полей выбранного профиля. То есть состав формы зависел не просто от штата, а от полной комбинации `country + state + revision + document type`.

#### Шаг 2. PHP собирал одну большую редактируемую форму

В режиме `Regular` код создавал обычные ручные поля имени, адреса, даты рождения и других данных человека. Затем он находил в metadata выбранного профиля специальные поля:

- `DAQ` — номер документа;
- `DBD` — дата выдачи;
- `DBA` — дата окончания;
- `DCK` — inventory control number;
- `DCF` — document discriminator;
- в некоторых профилях также `DCJ` и другие profile-specific поля.

Эти поля вставлялись в ту же форму в заранее заданном порядке. Они не были read-only: пользователь мог ввести или исправить значение вручную.

#### Шаг 3. Возле поддерживаемого поля появлялась отдельная кнопка `Generate`

Для каждого специального поля PHP смотрел metadata самого поля:

- ключ `randomHandler` означал вызов старого `getRandom`;
- ключ `calculateHandler` означал вызов старого `getCalculate`;
- строка-ключ handler-а содержала список зависимостей через запятую, например `DAJ,DBB`.

По этому списку PHP создавал JavaScript конкретной кнопки. Кнопка не запускала всю цепочку. Она обслуживала только одно поле или одну явно сгруппированную пару полей.

Фактические связи в UI были следующими:

| Поле формы | Что делала кнопка | Куда записывался ответ |
|---|---|---|
| `DAQ` | один вызов random для `DAQ` с текущими зависимостями из metadata | только `DAQ` |
| `DBD` | один grouped random с outputs `DBD, DBA` | сразу `DBD` и `DBA` |
| `DBA` | один calculate только для expiration date | только `DBA` |
| `DCK` | один calculate для inventory number | только `DCK` |
| `DCF` | один calculate для discriminator | только `DCF` |

Таким образом, `DBD + DBA` уже тогда рассматривались как один атомарный результат одного клика, а не как два независимых случайных запроса.

#### Шаг 4. При клике читались текущие значения зависимостей

Обработчик кнопки проходил по списку зависимостей handler-а и в момент клика читал текущие значения полей формы. Это важно: использовался не первоначальный снимок формы, а то, что пользователь успел ввести или исправить к этому моменту.

Legacy JavaScript склеивал данные в строку вида:

```text
DAJ/CA,DBB/07191994
```

Затем он отправлял эту строку и имя ожидаемого результата в один из PHP proxy-файлов. Такой строковый формат был технической особенностью старого frontend и не является новым контрактом.

#### Шаг 5. PHP proxy превращал клик в один вызов BarcodeGen

`getRandom.php`, `getRandom2.php`, `getCalculate.php`, `getCalculate2.php` и `getCalculate3.php` выполняли одинаковую роль:

1. разбирали строку `OBJ` на пары код/значение;
2. удаляли разделители из дат там, где это было нужно;
3. собирали старый BarcodeGen payload вида `values.req + values.res`;
4. вызывали ровно один endpoint `getRandom` или `getCalculate`;
5. переименовывали позиционный ответ в удобное для конкретного JavaScript обработчика поле.

Например:

- `getRandom.php` возвращал одно значение как `desc` для `DAQ`;
- `getRandom2.php` запрашивал `DBD` и `DBA`, затем возвращал их как `ISS` и `EXP`;
- три calculate proxy запрашивали отдельно `DBA`, `DCK` и `DCF`.

Это были не разные бизнес-операции. Это были пять почти одинаковых proxy-файлов из-за ограничений старой реализации.

#### Шаг 6. Ответ применялся как маленький patch формы

После успешного ответа JavaScript менял только output-поля нажатой кнопки:

- клик `DAQ` не менял даты;
- клик `DCK` не менял `DAQ`;
- клик дат менял одновременно `DBD` и `DBA`;
- весь остальной draft оставался без изменений.

После успеха старая кнопка скрывалась, но заполненное поле оставалось обычным редактируемым input. Пользователь мог вручную изменить сгенерированное значение перед финальной отправкой формы.

#### Шаг 7. Regular submit отправлял именно отредактированный полный draft

Для формы `Regular` обработчик в `barcodes/index.php` сериализовал `#GenBar` и отправлял его обратно в `stateGet.php`. Финальная ветка `stateGet.php`, начинающаяся с `if(isset($_POST['DCG']))`, читала из POST в том числе:

- `DAQ`;
- `DBD`;
- `DBA`;
- `DCK`;
- `DCF`;
- остальные ручные поля формы.

Она удаляла визуальные разделители дат, нормализовала регистр, собирала одну итоговую engine-карту и вызывала старый `genBarcode`. Поэтому значение, полученное отдельной кнопкой или исправленное пользователем после кнопки, действительно попадало в final render. На финальном Regular submit эта ветка не вызывала `getRandom`/`getCalculate` повторно.

Именно это поведение является прямым референсом нового Prepared Generate:

```text
текущий полный draft формы → нормализация/валидация → render
```

#### Шаг 8. `genBar.php` был отдельным Auto-подобным путём

Упомянутый `barcodes/genBar.php` реализовывал другой старый путь. Он:

1. читал базовые POST-поля, но инициализировал `DBD`, `DAQ`, `DBA`, `DCK`, `DCF` пустыми;
2. собирал все известные engine values в `$Msearch`;
3. повторно получал metadata профиля;
4. выбирал описания `DBD`, `DAQ`, `DCF`, `DCK`;
5. сортировал их в фиксированный порядок;
6. вызывал random/calculate;
7. после каждого результата пересобирал `$Msearch`, чтобы следующий шаг видел новое значение;
8. отправлял автоматически собранную engine-карту в `genBarcode`.

Этот файл полезен как подтверждение двух внутренних правил: существует одна общая карта значений, и результат предыдущего action становится input следующего. Но сам `genBar.php` является референсом Auto-последовательности, а не целевого Prepared submit.

Иными словами, в legacy существовали оба сценария:

- `Regular` в `stateGet.php`: отдельные кнопки, редактируемый полный draft, затем render текущих значений;
- `genBar.php`: автоматическая derive-последовательность, затем render.

Новый ручной Prepared должен повторить первый сценарий на современных Nuxt + BFF контрактах. Он не должен ссылаться на PHP и не должен переносить автоматический блок `genBar.php` в финальную кнопку.

#### Что именно берём из legacy

```text
metadata exact-профиля
  → известные зависимости одного действия
  → отдельная кнопка возле output-поля
  → один downstream вызов
  → маленький patch текущей формы
  → результат можно отредактировать вручную
```

#### Что именно не переносим

- ссылки и переходы на старый PHP frontend;
- вызовы `stateGet.php`, `getRandom*.php`, `getCalculate*.php` или `genBar.php` из нового приложения;
- AAMVA-коды в состоянии нового frontend;
- чтение зависимостей через DOM selectors;
- строки вида `DAJ/CA,DBB/07191994`;
- ручную склейку JSON;
- отдельный proxy-файл для каждого output;
- позиционный разбор downstream-ответа;
- скрытие кнопки навсегда после первого успеха;
- автоматический повторный random/calculate во время финального Prepared Generate;
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

### 2.3. Что уже есть в BFF

В BFF уже реализованы части Auto Flow, которые нужно переиспользовать, а не дублировать:

- `internal/usecase/chain_executor.go` — последовательное выполнение grouped random/calculate в Auto;
- `generate_usecase.go` — текущая развилка `auto`/`prepared`, Billing и render;
- `internal/domain/engine_fields.go` — semantic → AAMVA mapping;
- `internal/adapters/barcodegen/legacy/client.go` — BarcodeGen adapter;
- `internal/adapters/barcodegen/legacy/internal_client.go` — grouped derive и internal render;
- `configs/revisions/*.yaml` — version-controlled exact-profile definitions.

Prepared action должен переиспользовать низкоуровневый random/calculate adapter и правила профиля. Он не должен вызывать `ChainExecutor`, потому что executor специально продолжает цепочку, а ручной клик обязан выполнить ровно один action.

### 2.4. Что сейчас делает новый Nuxt frontend

Новый frontend из переданного репозитория — приложение на Nuxt 4, Vue 3 и Pinia. Изменения нужно делать в нём. Старый PHP frontend в новый проект не встраивается.

Основные текущие файлы:

- `app/pages/generating.vue` — экран выбора ревизии, построение формы и финальный submit;
- `app/shared/api/services/barcodeGeneration.service.ts` — вызовы revisions/schema/prepare/generate;
- `app/shared/api/types.ts` — `RevisionSchema`, `RevisionFieldSchema`, `PrepareResult`, `GenerateResult`;
- `app/shared/store/useBarcodeGeneration.ts` — сохранение schema и результатов генерации;
- `app/shared/ui/Input.vue`, `Select.vue`, `Button.vue` — существующие UI-примитивы.

Текущий код `generating.vue` выполняет другой сценарий:

1. При mount вызывает `listRevisions()` и автоматически выбирает первую ревизию.
2. Через `getSchema(name)` получает текущую BFF schema.
3. Вычисляет `formFields`, исключая всё, что перечислено в `schema.generatedFields`.
4. Создаёт flat-карту `form[field.name]` только для оставшихся полей.
5. На финальный клик собирает только непустые значения и сам преобразует browser date `YYYY-MM-DD` в engine-подобный `MMDDYYYY`.
6. Сначала вызывает `prepare(revision, cleaned)`, то есть запускает всю derive-цепочку.
7. Затем отправляет `prepare.data.draftFields` в `generate(mode="prepared")`.
8. Если `prepare` упал, код всё равно пытается вызвать `generate(mode="prepared")` с исходными полями.

Это не целевой ручной Prepared flow по следующим причинам:

- generated/calculated поля скрыты, поэтому пользователь не видит и не редактирует полный draft;
- рядом с полями нет отдельных Random/Calculate кнопок;
- один финальный клик сначала автоматически выполняет всю chain;
- frontend зависит от mixed `draftFields`, в котором могут находиться AAMVA-коды;
- frontend сам кодирует semantic dates в engine-формат;
- ошибка `prepare` проглатывается и меняет путь выполнения неявно;
- service и TypeScript types пока не содержат контракта одного action.

Целевая реализация — рефакторинг этого экрана и этого API service:

```text
list revisions
  → GET полной prepared schema выбранной revision
  → показать все semantic fields
  → отдельные необязательные action-кнопки меняют только свои outputs
  → финальная кнопка напрямую вызывает generate(mode="prepared")
```

`prepare()` остаётся в service для отдельного Auto/чернового сценария, если он нужен продукту, но экран ручного Prepared не вызывает его ни при загрузке, ни при action, ни перед финальным generate.

### 2.5. Что подтверждено текущим кодом, а что является целевым изменением

На момент проверки реальный flow такой:

1. `GET /api/v1/revisions/:revision/schema` отдаёт static BFF schema с semantic names; query `mode=prepared` сейчас handler-ом не используется.
2. Текущая `RevisionSchema` содержит `revision`, `displayName`, `baseInput`, `generatedFields`, `fields`, `groups`, но не содержит semantic action metadata.
3. Текущий frontend хранит значения по `field.name`, вызывает `prepare`, получает draft и затем отправляет его в `generate(mode="prepared")`.
4. `prepare` и Auto используют `ChainExecutor`; он добавляет engine keys, поэтому возвращаемый `draftFields` является смешанным public/engine объектом.
5. Текущий frontend работает именно потому, что финально отправляет этот mixed draft, а не чистый semantic Prepared payload.
6. В `generate_usecase.go` `filterRenderValues` сейчас вызывается **до** `NormalizeEngineFields`. Прямой `generate(mode="prepared")` с чистыми semantic keys может потерять значения на engine allowlist.
7. `GenerateRequest.Fields` пока является свободным `map[string]any`; raw engine keys не отклоняются строго на публичной границе.
8. Raw-only payload также не является контрактом: required-поля проверяются по semantic names. Mixed payload — временная утечка реализации, а не формат, который надо сохранить.
9. `POST /api/v1/barcode/action`, Prepared schema с action metadata и per-action validation сейчас отсутствуют.
10. В текущем `generate_usecase.go` условие `mode != "prepared"` должно быть заменено exact switch: неизвестный mode не имеет права запускать Auto.
11. В новом frontend отсутствуют action state, защита от устаревших ответов и стабильный idempotency key для повтора одного и того же финального запроса.

После реализации граница обязана быть следующей:

- browser, Vue state и публичные BFF endpoints используют только canonical semantic keys;
- публичные даты передаются как calendar date `YYYY-MM-DD`;
- AAMVA-коды и country-specific кодирование дат существуют только внутри BFF и BarcodeGen adapter;
- raw AAMVA/server-owned/unknown key во внешнем `fields` или `values` отклоняется до любых внешних вызовов;
- один Prepared action выполняет ровно один сохранённый action exact-профиля;
- финальный Prepared Generate выполняет validation, semantic → engine mapping, Billing и render, но не derive chain.

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

После выбора exact revision frontend делает один запрос только в BFF:

```http
GET /api/v1/revisions/US_CA_08292017/schema?mode=prepared
```

`US_CA_08292017` уже однозначно определяет страну, штат, дату ревизии и тип документа. Frontend не должен дополнительно присылать конкурирующие `country`, `state`, `revisionDate` или `documentType` в query. Если продуктовый selector строится из нескольких шагов, его итогом всё равно должен быть один exact revision ID из `GET /api/v1/revisions`.

Production BFF не запрашивает BarcodeGen metadata на каждый показ формы. Полная semantic schema, mappings, options, constants и action registry хранятся в version-controlled exact-profile BFF config.

BarcodeGen metadata используется только вне пользовательского runtime:

1. при первоначальной миграции профиля;
2. в compatibility fixture;
3. в CI-тесте, который обнаруживает расхождение checked-in config и BarcodeGen metadata.

Для такого offline-аудита BFF/tooling может вызвать BarcodeGen `barcodes` с `flag=false`, чтобы получить `top + down[type]`. Raw `abr`, `randomHandler` и `calculateHandler` после преобразования не публикуются browser-у.

### 4.1. Единственный целевой TypeScript-контракт schema

Существующие frontend types нужно расширить до следующего смысла. Названия свойств ниже нормативные; параллельные aliases добавлять нельзя.

```ts
type PreparedValue = string | number | boolean

type PreparedAction = {
  id: string
  type: "random" | "calculate"
  inputFields: string[]
  outputFields: string[]
}

type PreparedFieldOption = {
  value: PreparedValue
  label: string
}

type PreparedField = {
  name: string
  label: string
  type: "string" | "date" | "enum" | "number" | "boolean"
  control: "input" | "calendar" | "select" | "radio" | "toggle"
  required: boolean
  order: number
  fallbackValue?: PreparedValue
  options?: PreparedFieldOption[]
  validation?: {
    minLength?: number
    maxLength?: number
    pattern?: string
    minDate?: string
    maxDate?: string
  }
  action?: PreparedAction
}

type PreparedRevisionSchema = {
  revision: string
  displayName: string
  mode: "prepared"
  revisionEffectiveDate?: string
  fields: PreparedField[]
  groups: Array<{
    name: string
    label: string
    fields: string[]
  }>
}
```

Обязательные правила schema:

1. `revision` — тот же exact ID, который frontend передал в URL.
2. `fields` содержит **все** редактируемые semantic поля Prepared формы, включая generated/calculated outputs.
3. Полный ожидаемый набор равен `schema.fields.map(field => field.name)`. Отдельный второй список `expectedFields` не нужен.
4. `fields` отсортированы или сортируются frontend по `order`.
5. `type=date` использует публичный формат `YYYY-MM-DD`. `MMDDYYYY`/`YYYYMMDD` являются только внутренними engine-форматами BFF.
6. `type=enum` использует только typed `options[{value,label}]`; frontend показывает `label`, но сохраняет и отправляет `value` без преобразования.
7. `fallbackValue`, включая `""`, `0` и `false`, применяется только если свойство реально присутствует в JSON.
8. `action` присутствует только возле поля, у которого должна быть кнопка.
9. Все имена внутри `inputFields` и `outputFields` обязаны существовать в `fields` этой же schema.
10. Один action может вернуть несколько outputs. Для дат action находится у `issueDate`, но его `outputFields` равен `['issueDate', 'expirationDate']`.
11. Если для `expirationDate` exact-профиль поддерживает отдельный calculate, это отдельный `action` самого поля `expirationDate`.
12. `DAJ`, `DDB`, `QQQ`, другие AAMVA-коды и server-owned constants в `fields` не входят.

Внутри exact-profile config BFF дополнительно хранит данные, которые browser не получает:

- semantic → engine mapping;
- engine input/output каждого action;
- server-owned `DAJ/DDB/QQQ`;
- defaults/constants;
- render allowlist;
- required render fields;
- country-specific правила кодирования дат и единиц измерения.

### 4.2. Сокращённый пример schema для California DL

Пример намеренно показывает не всю форму, а только поля, необходимые для понимания action metadata. Реальный ответ обязан вернуть все поля профиля.

```json
{
  "revision": "US_CA_08292017",
  "displayName": "California Driver License (Aug 2017)",
  "mode": "prepared",
  "revisionEffectiveDate": "2017-08-29",
  "fields": [
    {
      "name": "dateOfBirth",
      "label": "Date of Birth",
      "type": "date",
      "control": "calendar",
      "required": true,
      "order": 5
    },
    {
      "name": "realId",
      "label": "Real ID",
      "type": "enum",
      "control": "select",
      "required": false,
      "order": 10,
      "options": [
        { "value": "", "label": "Not set" },
        { "value": "N", "label": "Non-compliant" },
        { "value": "F", "label": "Fully compliant" }
      ],
      "fallbackValue": ""
    },
    {
      "name": "documentNumber",
      "label": "Customer ID Number",
      "type": "string",
      "control": "input",
      "required": true,
      "order": 20,
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
      "required": true,
      "order": 21,
      "action": {
        "id": "random:documentDates",
        "type": "random",
        "inputFields": ["dateOfBirth", "realId"],
        "outputFields": ["issueDate", "expirationDate"]
      }
    },
    {
      "name": "expirationDate",
      "label": "Expiration Date",
      "type": "date",
      "control": "calendar",
      "required": true,
      "order": 22,
      "action": {
        "id": "calculate:expirationDate",
        "type": "calculate",
        "inputFields": ["dateOfBirth", "issueDate"],
        "outputFields": ["expirationDate"]
      }
    },
    {
      "name": "inventoryNumber",
      "label": "Inventory Control Number",
      "type": "string",
      "control": "input",
      "required": true,
      "order": 23,
      "action": {
        "id": "calculate:inventoryNumber",
        "type": "calculate",
        "inputFields": ["issueDate", "documentNumber"],
        "outputFields": ["inventoryNumber"]
      }
    }
  ],
  "groups": []
}
```

Frontend не видит engine dependencies и не разбирает исходный `randomHandler`. Например, для California action дат browser отправляет `dateOfBirth` и `realId`; BFF сам добавляет внутренние `DAJ=CA` и `DDB=08292017`, затем маппит semantic values в `DBB/DDA` и outputs `DBD/DBA` обратно в `issueDate/expirationDate`.

### 4.3. Зачем хранить actions в BFF

Полная форма и разрешённые actions сохраняются в version-controlled exact-profile registry BFF.

Причины:

1. Auto и Prepared используют одни и те же проверенные внутренние dependencies.
2. Frontend не может подменить inputs или outputs.
3. Каждая кнопка имеет короткое и понятное semantic правило.
4. Compatibility tests сравнивают registry с `randomHandler/calculateHandler` BarcodeGen и ловят расхождение до релиза.
5. Не нужен runtime metadata call и отдельный длинный Prepared-chain.

Если compatibility fixture и сохранённый action не совпали, deployment/test должен завершиться ошибкой. Production BFF не угадывает правило по live metadata.

## 5. Состояние и рендер полной формы в новом frontend

### 5.1. Что хранить

На странице должен существовать один активный exact revision и одна flat semantic-карта его значений:

```ts
type PreparedValue = string | number | boolean

type ActionRuntimeState = {
  status: "idle" | "loading" | "error"
  requestId?: string
  message?: string
}

type PreparedFormState = {
  revision: string
  schema: PreparedRevisionSchema
  values: Record<string, PreparedValue>
  fieldVersions: Record<string, number>
  actions: Record<string, ActionRuntimeState>
  formEpoch: number
}
```

`fieldVersions` и `formEpoch` — frontend-only техническое состояние для защиты от устаревших ответов. Они не отправляются в BFF.

Пример `values`:

```json
{
  "firstName": "JOHN",
  "middleName": "",
  "lastName": "SMITH",
  "dateOfBirth": "1994-07-19",
  "documentNumber": "",
  "issueDate": "",
  "expirationDate": "",
  "inventoryNumber": "",
  "documentDiscriminator": ""
}
```

Страна, штат, дата ревизии и document type не дублируются в `values`. Они уже зафиксированы exact ID `revision`; BFF восстанавливает server-owned context из registry.

### 5.2. Как инициализировать форму после schema response

Алгоритм должен быть один и детерминированный:

1. Увеличить `formEpoch`.
2. Отменить или логически инвалидировать незавершённые schema/action запросы старой ревизии.
3. Очистить прежние field errors, action errors и результат barcode.
4. Для каждого `schema.fields` создать ключ `values[field.name]`.
5. Если JSON field **содержит** свойство `fallbackValue`, записать его буквально, включая `""`, `0` или `false`.
6. Иначе записать `""` как незаполненное UI-значение.
7. Создать `fieldVersions[field.name] = 0`.
8. Создать `actions[action.id] = {status: 'idle'}` для каждого уникального action.
9. Рендерить поля по `groups`; поля, отсутствующие в группах, рендерить после групп по `order`.

Нельзя использовать проверку `if (field.fallbackValue)`: она потеряет `0`, `false` и пустую строку. Нужна проверка наличия свойства, например `Object.hasOwn(field, 'fallbackValue')`.

### 5.3. Как рендерить поле

- `control=input`, `type=string` → обычный текстовый `Input`.
- `control=input`, `type=number` → numeric input, но пустое состояние остаётся `""`; в число значение превращается только при валидном вводе/submit.
- `control=calendar`, `type=date` → browser date control со значением `YYYY-MM-DD`.
- `control=select` → `Select` из typed `options`; хранится `option.value`, показывается `option.label`.
- `control=radio` и `control=toggle` → компонент обязан сохранять фактическое typed value, а не текст label.
- `required=true` → визуальный required marker и клиентская проверка перед submit.
- `validation` → атрибуты/подсказки frontend и повторная обязательная проверка BFF.
- `action` → кнопка в той же строке/группе, что и поле. Текст кнопки определяется `action.type`: `Random` или `Calculate`.

Generated/calculated output — такой же редактируемый input. Кнопка является удобным способом заполнения, а не единственным источником значения.

В `generating.vue` запрещено сохранять текущий фильтр:

```ts
.filter(field => !generated.has(field.name))
```

Prepared должен рендерить весь `schema.fields`. Если Auto и Prepared живут на одном экране, фильтрация допускается только в явно выбранной ветке Auto.

### 5.4. Когда action-кнопка enabled

Кнопка видима всегда, когда у поля есть `action`. Она disabled, если выполняется хотя бы одно условие:

1. этот action уже находится в `loading`;
2. текущая revision/schema ещё загружается;
3. хотя бы один `action.inputFields` не заполнен;
4. хотя бы один input уже известен frontend как невалидный.

Проверка «заполнено» не должна использовать обычный truthy check:

- string заполнен, если `trim() !== ""`;
- number заполнен, если это конечное число;
- boolean всегда является заданным значением, включая `false`.

Disabled в UI — только удобство. BFF в любом случае повторно проверяет inputs.

### 5.5. Ручное редактирование и зависимости

При любом пользовательском изменении поля frontend:

1. записывает новое semantic value;
2. увеличивает `fieldVersions[fieldName]`;
3. очищает ошибку только этого поля;
4. пересчитывает enabled/disabled зависимых кнопок.

Frontend **не очищает** ранее полученные outputs автоматически. Например, изменение `issueDate` не удаляет `inventoryNumber`. Пользователь либо оставляет полный draft как явный override, либо вручную нажимает нужную кнопку ещё раз.

Если action собирается заменить уже непустое output-поле, frontend сначала показывает подтверждение со списком заменяемых labels. При отмене запрос не отправляется.

### 5.6. Защита от race conditions

В момент клика frontend сохраняет:

- текущий `formEpoch`;
- уникальный `requestId`;
- текущие версии всех `inputFields`;
- текущие версии всех `outputFields`.

Ответ применяется только если:

1. revision и `formEpoch` не изменились;
2. `requestId` всё ещё является последним запросом этого action;
3. ни один input и ни один output не был изменён после клика.

Если любое условие нарушено, ответ молча считается устаревшим и не меняет форму. Для grouped action patch применяется целиком или не применяется вообще; частичное обновление `issueDate` без `expirationDate` запрещено.

## 6. Нажатие одной кнопки Random/Calculate

### 6.1. Что отправляет новый frontend

Frontend берёт action только из загруженной schema. Он не конструирует `actionId`, inputs или outputs по имени поля.

Для запроса frontend создаёт `values`, копируя из формы только имена из `action.inputFields`. Весь draft отправлять не нужно. Outputs, engine dependencies и server-owned context в request не отправляются.

```http
POST /api/v1/barcode/action
Content-Type: application/json
```

```json
{
  "revision": "US_CA_08292017",
  "actionId": "calculate:inventoryNumber",
  "values": {
    "issueDate": "2023-04-16",
    "documentNumber": "A1234567"
  }
}
```

Для action без публичных inputs request содержит `"values": {}`. Frontend не добавляет туда `DAJ`, потому что BFF берёт штат из exact revision config.

Один клик выполняет следующий строгий алгоритм BFF:

1. Проверить JSON shape и обязательные `revision`, `actionId`, `values`.
2. Загрузить enabled exact-profile config по `revision`; неизвестная/disabled revision завершается до BarcodeGen.
3. Найти action с точным `actionId` в Prepared registry этого профиля.
4. Убедиться, что набор ключей `values` является точным допустимым semantic input-набором action: неизвестные, output-only, engine и server-owned keys запрещены.
5. Проверить presence, тип, option membership, pattern и date rules каждого input.
6. Добавить server-owned context (`DAJ`, `DDB`, `QQQ`, constants/defaults) из профиля.
7. Преобразовать только разрешённые semantic inputs в engine values. Даты из `YYYY-MM-DD` кодируются по стране внутри BFF.
8. Выполнить один логический adapter action: random или calculate. Только grouped action дат может повторить тот же random до 10 раз по правилам раздела 6.4.
9. Не запускать `ChainExecutor` и не продолжать к следующему action.
10. Проверить downstream status, JSON shape и наличие всех объявленных engine outputs.
11. Отбросить неожиданные outputs, даже если BarcodeGen их вернул.
12. Провалидировать outputs по правилам exact-профиля.
13. Атомарно преобразовать outputs обратно в semantic patch.
14. Вернуть patch. Не вызывать Quote, Billing, render, history или сохранение barcode.

Action endpoint является stateless: BFF не хранит черновик между кликами. Источником текущего draft остаётся Vue state.

### 6.2. Успешный ответ и его применение

```json
{
  "success": true,
  "revision": "US_CA_08292017",
  "actionId": "calculate:inventoryNumber",
  "values": {
    "inventoryNumber": "23106A12345670401"
  }
}
```

Перед применением frontend проверяет:

1. ответ относится к текущей `revision`;
2. `actionId` совпадает с отправленным;
3. ключи `response.values` точно совпадают с `action.outputFields`;
4. race-condition проверки раздела 5.6 пройдены.

После этого frontend атомарно применяет patch и увеличивает version каждого изменённого output. Он не заменяет весь draft объектом ответа BFF.

```ts
for (const output of action.outputFields) {
  form.values[output] = response.values[output]
  form.fieldVersions[output] += 1
}
```

### 6.3. Ошибки action endpoint

Нормативное поведение:

| HTTP | `error.code` | Когда |
|---|---|---|
| `400` | `VALIDATION_ERROR` | malformed JSON, неизвестный semantic key, AAMVA/server-owned key, неверный тип верхнего уровня |
| `404` | `REVISION_NOT_FOUND` | exact revision не существует или недоступна пользователю |
| `422` | `ACTION_NOT_ALLOWED` | action не объявлен в этом exact-профиле |
| `422` | `ACTION_INPUT_MISSING` | отсутствует или пуст обязательный input |
| `422` | `ACTION_INPUT_INVALID` | input нарушает type/options/pattern/date rule |
| `422` | `ACTION_OUTPUT_INVALID` | BarcodeGen вернул полный, но недопустимый результат |
| `502` | `BARCODEGEN_BAD_RESPONSE` | downstream вернул ошибку, невалидный JSON или неполные outputs |
| `504` | `BARCODEGEN_TIMEOUT` | истёк timeout downstream action |

Пример отсутствующих inputs:

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

Frontend показывает ошибку возле action и соответствующих полей. Он не очищает прежние outputs и не запускает fallback action.

Неизвестный `actionId` никогда не интерпретируется как имя engine step. Любой AAMVA key, например `DAQ`, возвращает `400 VALIDATION_ERROR` до adapter-а.

### 6.4. Атомарный action дат

`random:documentDates` — один логический action и один semantic patch:

```json
{
  "success": true,
  "revision": "US_CA_08292017",
  "actionId": "random:documentDates",
  "values": {
    "issueDate": "2023-04-16",
    "expirationDate": "2028-07-19"
  }
}
```

Внутри adapter работает с `DBD + DBA`, но эти ключи не пересекают публичную BFF-границу. BFF применяет правила `ISS_DBD_FLOW.md`:

1. Кодирует inputs в engine-формат страны.
2. Запрашивает `DBD + DBA` одним grouped random.
3. Строго разбирает обе даты как calendar dates.
4. Проверяет `revisionEffectiveDate <= issueDate <= today`.
5. Проверяет `issueDate < expirationDate`.
6. При невалидной паре отбрасывает **обе** даты и повторяет тот же grouped random.
7. Делает максимум 10 попыток в рамках одного пользовательского action.
8. После успеха возвращает обе даты в `YYYY-MM-DD`.
9. После десятой ошибки возвращает error без `values`; прежние значения Vue-формы сохраняются.

Повтор даты — единственное разрешённое внутреннее повторение одного action. BFF не переходит после него к `DCK`, `DCF` или любому другому action.

### 6.5. Два profile-specific примера

**Kentucky document number:** его кнопка disabled, пока не заполнены все публичные inputs exact-action.

```json
{
  "revision": "US_KY_08312018",
  "actionId": "random:documentNumber",
  "values": {
    "dateOfBirth": "1994-07-19",
    "lastName": "SMITH",
    "issueDate": "2023-04-16"
  }
}
```

```json
{
  "success": true,
  "revision": "US_KY_08312018",
  "actionId": "random:documentNumber",
  "values": { "documentNumber": "S12345678" }
}
```

BFF сам добавляет `DAJ=KY` и другие server-owned values из профиля.

**Washington:** это два явных клика пользователя, а не одна автоматически продолжаемая цепочка.

```text
клик 1: random:auditCode
результат: форма получила auditCode
кнопка 2 стала enabled
клик 2: calculate:documentDiscriminator
```

После первого ответа frontend записывает только `auditCode`. Он не вызывает второй action автоматически. Внутренние engine-правила при этом остаются `[DAJ] → [DCJ]`, затем `[DAJ, DAQ, DCJ] → [DCF]`.

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

### 8.1. Что делает frontend перед запросом

Финальная кнопка не вызывает `prepare()` и не нажимает actions программно. Она выполняет только следующие шаги:

1. Берёт текущую schema и текущие `values`.
2. Строит новый `fields` объект, проходя **только** по `schema.fields`.
3. Для каждого schema field копирует значение, включая `""`, `0` и `false`.
4. Не добавляет техническое состояние Vue/Pinia, labels, action metadata или context selector-а.
5. Не преобразует `YYYY-MM-DD` в AAMVA date. Публичные dates остаются ISO calendar dates.
6. Выполняет клиентскую validation для быстрого feedback.
7. Если frontend validation прошла, создаёт immutable snapshot payload.
8. Создаёт один `X-Idempotency-Key` для этого snapshot и сохраняет его до окончательного ответа.
9. Блокирует повторный submit того же snapshot, пока запрос выполняется.
10. Вызывает сразу `generate(mode="prepared")`.

Если network request оборвался без окончательного HTTP-ответа, повтор **того же snapshot** использует тот же idempotency key. Если пользователь изменил revision, units или любое field value, это новый snapshot и для него создаётся новый key.

Текущий `barcodeGeneration.service.ts` генерирует key внутри каждого вызова `generate()`. Его нужно изменить так, чтобы page/use-case мог передать уже созданный key и переиспользовать его при безопасном retry.

```http
POST /api/v1/barcode/generate
X-Idempotency-Key: <stable-key-for-this-snapshot>
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
    "dateOfBirth": "1994-07-19",
    "street": "123 MAIN ST",
    "city": "LOS ANGELES",
    "zipCode": "90001",
    "sex": 1,
    "realId": "F",
    "documentNumber": "A1234567",
    "issueDate": "2023-04-16",
    "expirationDate": "2028-07-19",
    "inventoryNumber": "23106A12345670401",
    "documentDiscriminator": "04/16/2023ABCDE/1234/28",
    "vehicleClass": "C",
    "restrictions": "NONE",
    "endorsements": "NONE",
    "height": 70,
    "weightPounds": 180,
    "eyeColor": "BRO",
    "hairColor": "BRO",
    "organDonor": false,
    "veteran": false
  }
}
```

### 8.2. Точный порядок обработки в BFF

BFF в ветке `prepared`:

1. Аутентифицирует пользователя и проверяет idempotency key по общему generate-контракту.
2. Проверяет exact `mode` через `switch`; неизвестный mode отклоняется.
3. Загружает immutable enabled exact-profile config по `revision`.
4. Проверяет верхнеуровневые `barcodeType`, `units`, `confirmed` и limits профиля.
5. Проверяет, что каждый ключ `fields` является canonical semantic field текущей schema.
6. Отклоняет raw AAMVA, server-owned, alias и неизвестные keys до Quote/Billing.
7. Проверяет required presence. Пробельная string считается пустой; `0` и `false` не считаются отсутствующими.
8. Проверяет типы, typed options membership, min/max, pattern и profile-specific правила.
9. Парсит semantic dates как calendar dates, проверяет реальность даты и ограничения возраста/ревизии.
10. Проверяет совместные правила: `revisionEffectiveDate <= issueDate <= today` и `issueDate < expirationDate`.
11. **Не вызывает** `prepare`, `ChainExecutor`, random или calculate.
12. Маппит semantic draft в новую engine-карту.
13. Кодирует даты и единицы измерения по country/profile rules.
14. Подставляет server-owned `DAJ/DDB/QQQ` и immutable defaults/constants из profile config.
15. Строит render payload только по engine `renderAllowlist`.
16. Проверяет `requiredRenderFields` уже на готовой engine-карте.
17. Только после всех предыдущих проверок вызывает Quote и Billing Block.
18. Вызывает internal PDF417 render ровно один раз.
19. При успехе делает Capture, записывает history и возвращает barcode list.
20. При ошибке render делает Release и возвращает нормализованную ошибку.

Псевдокод целевого ветвления:

```go
switch req.Mode {
case "auto":
    values = chainExecutor.ExecuteGrouped(ctx, profile, req.Fields)
case "prepared":
    semanticDraft = validateCompletePreparedFields(profile, req.Fields)
    values = mapPreparedSemanticToEngine(profile, semanticDraft)
default:
    return ErrUnsupportedMode
}
```

Engine allowlist применяется **после** semantic mapping. Нельзя сначала фильтровать semantic keys engine allowlist-ом. Нельзя оставлять проверку `if mode != "prepared"`, потому что опечатка в mode иначе запустит Auto.

### 8.3. Успешный ответ BFF

Ответ сохраняет уже используемую новым frontend структуру `barcodes[]`:

```json
{
  "success": true,
  "buildId": "build-7c7a",
  "batchId": "batch-42",
  "barcodes": [
    {
      "url": "https://.../barcodes/7c7a.png",
      "format": "pdf417",
      "generationId": "7c7a"
    }
  ],
  "computed": [],
  "skipped": [],
  "billing": {
    "unitPrice": 0.5,
    "totalCost": 0.5,
    "currency": "USD"
  }
}
```

`computed` и `skipped` в Prepared обычно пустые, потому что финальный запрос ничего не вычисляет. Их можно сохранить для совместимости общего `GenerateResult`, но frontend не должен ожидать там action results.

### 8.4. Неполная форма

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

### 8.5. Невалидное значение

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

### 8.6. Raw AAMVA key во внешнем payload

Если клиент прислал, например, `"DAQ": "A1234567"` вместо `"documentNumber": "A1234567"`, BFF отвечает `400 VALIDATION_ERROR` с `details.unknownFields=["DAQ"]`. Запрос не доходит до Quote, Billing, helper или render.

## 9. Пошаговый план изменения BFF и нового frontend

Рекомендуемый и проверяемый порядок реализации:

```text
BFF domain/config
  → BFF action endpoint
  → BFF prepared generate fix
  → BFF contract tests
  → frontend types/service
  → frontend controls/page
  → frontend tests
  → browser acceptance
```

Не начинать frontend с временных PHP вызовов или hardcoded action matrix. Frontend подключается к semantic BFF schema/action contract.

### 9.1. BFF: domain и exact-profile config

1. Не добавлять `mode=standard`. Публичные mode остаются только `auto` и `prepared`; `prepare` является отдельной операцией, а не третьим generate mode.
2. Зафиксировать canonical semantic vocabulary. Для generated fields использовать только:
   - `documentNumber`, не `idNumber` и не `dlNumber`;
   - `expirationDate`, не `expiryDate`;
   - `inventoryNumber`;
   - `documentDiscriminator`;
   - `auditCode`.
   Существующие общие имена `zipCode`, `height`, `weightPounds` сохранить едиными во всех schema/profile files.
3. Расширить `internal/domain/revision_schema.go`:
   - добавить `Control` в `FieldSchema`;
   - заменить конкурирующие `Options []string` + `OptionItems []FieldOption` одним JSON-полем `Options []FieldOption`;
   - сделать `FieldOption.Value` typed (`any` в Go DTO), чтобы JSON не превращал `0`/`false` в строки;
   - добавить optional public `Action`;
   - добавить root `Mode`; ответ на `?mode=prepared` всегда содержит `"mode":"prepared"`.
4. Добавить внутренний тип action, который хранит обе стороны контракта и явно указывает место кнопки:

```go
type PreparedActionDefinition struct {
    ID             string
    Kind           string   // random | calculate
    DisplayField   string   // semantic field, возле которого показана кнопка
    SemanticInput  []string
    SemanticOutput []string
    EngineInput    []string
    EngineOutput   []string
}
```

`DisplayField` обязан входить в `SemanticOutput`. Для grouped dates это `issueDate`; отдельный calculate expiration размещается у `expirationDate`.

5. Хранить `PreparedActions` внутри того же immutable `RevisionConfig`, где уже находятся fields, generation steps, defaults и render allowlist.
6. Не строить public action динамически из request-а. `ToPreparedSchema()` проецирует definition на `FieldSchema.Action` поля `DisplayField`: frontend получает только `ID/Kind/SemanticInput/SemanticOutput`, engine-массивы не получает.
7. Для каждого из 55 exact profiles перенести actions из матрицы раздела 7. Если engine dependency является server-owned (`DAJ`, `DDB`, `QQQ`, constant), она не попадает в `SemanticInput`.
8. Добавить generated/calculated output fields в `Fields` каждого профиля, чтобы Prepared schema была полной.
9. Не добавлять Prepared actions для имени, даты рождения и адреса, даже если старый BarcodeGen metadata содержит такие handlers.
10. Добавить validation при загрузке config: уникальные action IDs, известные semantic fields, известные engine mappings, непустые outputs, отсутствие дубликатов, согласованность field action и registry definition.
11. BarcodeGen `flag=false` использовать только в offline compatibility tests, а не как runtime source of truth.

### 9.2. BFF: action endpoint

1. Добавить request/response DTO с точными полями `revision`, `actionId`, `values`.
2. Добавить route `POST /api/v1/barcode/action` в существующий `/api/v1` router и применить ту же auth middleware, что у generation endpoints.
3. Добавить отдельный `PreparedActionUseCase`; не размещать этот flow внутри HTTP handler.
4. Use case получает profile store и существующий BarcodeGen random/calculate adapter.
5. Use case реализует порядок из раздела 6: lookup → key/type validation → server context → semantic mapping → один logical action → output validation → semantic patch.
6. Не инжектировать и не вызывать Billing, render client, history repository или `ChainExecutor` из этого use case.
7. Для date action переиспользовать единый helper атомарной проверки `DBD/DBA` и лимит 10 попыток.
8. Нормализовать downstream errors в коды таблицы 6.3; не отдавать browser-у сырой body BarcodeGen.
9. Логировать revision/action ID/latency/attempt count, но не логировать полный PII draft.
10. Добавить route/use-case tests до подключения frontend.

### 9.3. BFF: финальный generate(mode=prepared)

1. В `generate_usecase.go` заменить условие вида `mode != "prepared"` на exact `switch`.
2. Разделить prepared validation и engine mapping на явные функции; не пропускать публичный map напрямую в render filter.
3. Сначала разрешить только field names из exact schema. Это автоматически отклоняет AAMVA, server-owned и старые aliases.
4. Проверить full semantic draft по разделу 8.2.
5. Затем создать новую engine-карту через canonical semantic mapping.
6. Затем применить defaults/constants и country-specific преобразования.
7. Только потом применить `renderAllowlist` и проверить `requiredRenderFields`.
8. Убедиться тестом, что prepared branch не вызывает `ChainExecutor`, random, calculate или `prepare`.
9. Все validation errors вернуть до Quote/Billing.
10. Сохранить существующий общий ответ `GenerateResult` с `barcodes[]`, чтобы не ломать отображение результата в новом frontend.

### 9.4. Frontend: `app/shared/api/types.ts`

1. Добавить `PreparedAction`, typed `PreparedFieldOption` и `ActionResult`.
2. Расширить `RevisionFieldSchema` свойствами `control` и `action`.
3. Зафиксировать public date как `YYYY-MM-DD` в типах/тестовых fixtures.
4. Удалить зависимость Prepared UI от `generatedFields`; это Auto metadata, а не список скрытых полей Prepared.
5. Не добавлять AAMVA-коды в union/types frontend.

Нужные DTO:

```ts
interface PreparedActionRequest {
  revision: string
  actionId: string
  values: Record<string, PreparedValue>
}

interface PreparedActionResult {
  success: true
  revision: string
  actionId: string
  values: Record<string, PreparedValue>
}
```

### 9.5. Frontend: `barcodeGeneration.service.ts`

1. Сохранить существующие `listRevisions()`, `getSchema()` и `generate()`.
2. Изменить `getSchema(revision, mode)`, чтобы Prepared page явно вызывала `/revisions/{revision}/schema?mode=prepared`.
3. Добавить `runAction(payload)` → `POST /barcode/action`.
4. В mock mode возвращать patch только для outputs mock-action, а не весь draft.
5. Изменить `generate` так, чтобы caller мог передать `idempotencyKey`; service не должен всегда создавать новый key при retry.
6. Не вызывать `prepare()` внутри `generate()` или `runAction()`.
7. Не добавлять legacy PHP URLs ни в runtime config, ни в service.

### 9.6. Frontend: `app/shared/ui/Select.vue` и остальные controls

Текущий `Select.vue` принимает только `string[]`, показывает строку как label/value и использует `modelValue || ''`. Это не подходит для typed options: `0` и `false` будут потеряны, а label нельзя отделить от value.

Нужно сделать одно из двух: расширить существующий `Select.vue` или создать отдельный typed select. Целевое поведение в обоих случаях одинаковое:

1. Props принимают `Array<{value: string | number | boolean; label: string}>`.
2. UI показывает `label`.
3. `update:modelValue` возвращает исходный typed `value`, а не всегда string из DOM.
4. Empty-state определяется через `modelValue === null || modelValue === undefined`, а не truthy check.
5. Для native `<select>` нельзя полагаться на строковый DOM value для восстановления типа; component должен найти исходный option по стабильному внутреннему key/index и emit его `option.value`.
6. Revision selector тоже переводится на typed options: `value = revision.name`, `label = revision.displayName || revision.name`.
7. Страница хранит выбранный revision ID напрямую. Текущая схема `selectedLabel → find first matching label` удаляется, потому что display labels не обязаны быть уникальными.
8. `Input.vue` может продолжать emit string; `usePreparedForm` явно преобразует валидный number при обновлении/submit. Date input хранит строку `YYYY-MM-DD`.
9. `Button.vue` используется с отдельным `loading` каждого action; loading финального generate не подменяет action loading.

### 9.7. Frontend: `app/pages/generating.vue`

Текущий файл нужно изменить в следующем порядке:

1. Оставить загрузку revision list и schema.
2. Заменить `selectedLabel`/поиск по display label на `selectedRevision`, содержащий exact `revision.name`.
3. Для selector-а строить typed options `{value: revision.name, label: revision.displayName || revision.name}`.
4. При запросе schema передавать `mode=prepared`.
5. Удалить фильтрацию `generatedFields`; `formFields` должен содержать все `schema.fields`, отсортированные по `order`.
6. Инициализировать `values` по алгоритму 5.2 с сохранением falsy fallback values.
7. Удалить `toEngineDate()`: browser date уже имеет нужный публичный ISO-формат.
8. Заменить текущий `buildFields()`, который пропускает `""`, на сбор полного объекта по `schema.fields`.
9. Рендерить control и typed options из schema, а не угадывать UI только по `field.type`.
10. Возле поля с `field.action` рендерить `Random`/`Calculate` button.
11. Добавить per-action loading/error state; общий `loading` оставить только для schema/final generate.
12. Реализовать dependency disabling, overwrite confirmation и race-condition правила раздела 5.
13. В action click вызвать только `service.runAction()` и применить только объявленный patch.
14. Не запускать следующий action автоматически.
15. Полностью удалить из `onGenerate()` вызов `service.prepare()` и его silent `catch` fallback.
16. Финальный `onGenerate()` вызывает только `service.generate({mode: 'prepared', fields: fullSemanticFields})`.
17. При field-level BFF errors связать `error.fields[].name` с соответствующим control; общий error показать над финальной кнопкой.
18. После успеха сохранить `res.data.barcodes` через существующий `store.addBarcodes()`.

Чтобы `generating.vue` не превратился в монолит, допустимо и предпочтительно вынести:

- `PreparedFieldRow.vue` — control + label + field error + action button;
- `usePreparedForm.ts` — initialization, field versions, action state, race protection, full snapshot;
- `PreparedOverwriteDialog.vue` — подтверждение перезаписи нескольких outputs.

Это компоненты нового Nuxt frontend. Они не оборачивают и не открывают старый PHP frontend.

### 9.8. Frontend: store, i18n и error UX

1. `useBarcodeGenerationStore` можно оставить для schema/result history; активную незавершённую форму допустимо держать локально в page/composable.
2. Не сохранять PII draft в localStorage/persisted Pinia.
3. При смене revision очищать barcode result и инвалидировать старые action responses.
4. Добавить i18n keys для `Random`, `Calculate`, missing dependencies, overwrite confirmation, stale/failed action и prepared validation errors.
5. Action error не должен заменять общий final-generate error и наоборот.
6. Loading одного action не должен блокировать ручное редактирование независимых полей; final submit блокируется, пока выполняется любой action, чтобы snapshot не был неоднозначным.

## 10. Обязательные проверки

### 10.1. Static config/schema tests BFF

Для каждого из 55 профилей тест автоматически проверяет:

- exact revision загружается и имеет уникальный ID;
- публичная schema содержит только canonical semantic field names;
- каждое required/render поле имеет утверждённый источник;
- каждый action ID уникален внутри профиля;
- semantic inputs/outputs существуют в `Fields`;
- внутренние engine inputs/outputs точно совпадают с матрицей раздела 7;
- action projection в public schema не содержит engine keys;
- generated/calculated output присутствует как редактируемое поле;
- actions имени, DOB и адреса отсутствуют;
- enum options typed, а fallback входит в options;
- group fields существуют и не дублируются;
- `revisionEffectiveDate` валидна;
- ON/AB не получают выдуманные US actions.

### 10.2. Action use-case и HTTP contract tests BFF

Минимальный набор:

1. Разрешённый action получает только свои semantic inputs и возвращает только semantic outputs.
2. Action без публичных inputs работает с `values={}` и получает server context из профиля.
3. Неизвестный action возвращает `422 ACTION_NOT_ALLOWED` без downstream call.
4. Missing input возвращает `422 ACTION_INPUT_MISSING`.
5. Invalid option/date/pattern возвращает `422 ACTION_INPUT_INVALID`.
6. Raw AAMVA, server-owned, unknown и output-only key возвращает `400 VALIDATION_ERROR`.
7. Лишний semantic input не отправляется downstream и не игнорируется молча.
8. Неожиданный engine output не попадает в response.
9. Неполный downstream output превращается в `502 BARCODEGEN_BAD_RESPONSE`.
10. Action не вызывает Billing, render, history или следующий action.
11. `DBD/DBA` retry ограничен 10 попытками и всегда атомарен.
12. Public date input/output имеет `YYYY-MM-DD`; adapter получает country-specific engine format.
13. Логи не содержат полный PII request body.

### 10.3. Финальный Prepared Generate tests BFF

Нужны отдельные spy/mock assertions:

- clean full semantic payload успешно доходит до render;
- semantic mapping происходит до engine allowlist;
- prepared branch ни разу не вызывает `prepare`, `ChainExecutor`, random или calculate;
- unknown mode не попадает в Auto;
- raw AAMVA/server-owned/alias/unknown key отклоняется до Quote/Billing;
- incomplete/invalid prepared payload отклоняется до Quote/Billing;
- `""`, `0` и `false` сохраняются и валидируются правильно;
- ISO dates кодируются в `MMDDYYYY` для US и `YYYYMMDD` для ON/AB только внутри BFF;
- immutable profile constants нельзя подменить;
- render получает только allowlisted engine keys;
- Billing Block происходит после validation, Capture после render success, Release после render failure;
- одинаковый idempotency key и одинаковый snapshot не создают двойной charge/render/history record;
- успешный ответ соответствует существующему `GenerateResult` с `barcodes[]`.

### 10.4. Frontend unit/component tests

Покрыть новый `usePreparedForm`/страницу:

1. Все schema fields рендерятся; generated outputs не фильтруются.
2. `fallbackValue` сохраняет `""`, `0` и `false`.
3. Enum показывает label и отправляет typed value.
4. Date остаётся `YYYY-MM-DD`; `toEngineDate` не вызывается и отсутствует.
5. Action button visible только при наличии `field.action`.
6. Missing dependency делает action disabled; `false` считается заполненным boolean.
7. Один action показывает loading только для себя.
8. Успех меняет только `outputFields`.
9. Error не очищает существующее значение.
10. Grouped dates применяются обе или не применяются ни одна.
11. Изменение input/output во время запроса делает ответ stale.
12. Смена revision делает старый ответ stale.
13. Следующий action после patch только разблокируется, но не вызывается.
14. Непустой output требует overwrite confirmation.
15. Final snapshot содержит все schema fields, включая falsy values.
16. Final click не вызывает `prepare()`.
17. Retry неизменённого snapshot использует прежний idempotency key; изменённый snapshot получает новый.
18. В HTTP payload отсутствуют AAMVA-коды и frontend runtime state.

### 10.5. Browser acceptance flow

На реальном Nuxt экране проверить минимум California и Washington:

**California:**

1. Выбрать revision.
2. Убедиться, что видны ручные и generated fields.
3. Заполнить DOB/Real ID.
4. Нажать Random dates и увидеть две даты.
5. Вручную исправить expiration date.
6. Нажать Calculate inventory; остальные поля не должны измениться.
7. Нажать финальную Generate.
8. В Network убедиться: перед `/barcode/generate` не было `/barcode/prepare`; final body содержит semantic ISO dates.
9. Убедиться, что появился `barcodes[0].url`.

**Washington:**

1. Нажать Random audit code.
2. Убедиться, что discriminator request не отправился автоматически.
3. Убедиться, что его кнопка стала enabled.
4. Нажать её отдельно и получить только discriminator patch.

### 10.6. Особые profile fixtures

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

Реализация считается завершённой только если одновременно выполняются все пункты:

1. Изменён новый Nuxt frontend; пользователь не перенаправляется на старый PHP frontend.
2. В runtime нового frontend нет URL или вызовов `stateGet.php`, `getRandom*.php`, `getCalculate*.php`, `genBar.php`.
3. В системе остаются только generate modes `auto` и `prepared`.
4. Prepared schema показывает полную semantic-форму exact revision, включая generated/calculated outputs.
5. Frontend хранит и отправляет только canonical semantic keys и ISO calendar dates.
6. AAMVA-коды и engine date formats существуют только внутри BFF/BarcodeGen boundary.
7. Raw AAMVA, server-owned, old alias или unknown key отклоняется до Quote/Billing.
8. Пользователь может полностью заполнить Prepared форму без использования actions.
9. Имя, DOB и адрес не имеют Prepared actions и никогда не генерируются этими кнопками.
10. Каждая показанная кнопка получена из checked-in action exact-профиля.
11. Один клик выполняет один logical action; date retry не запускает другие actions.
12. Следующий action никогда не запускается автоматически.
13. BFF не доверяет присланным клиентом dependencies/outputs.
14. Action request содержит `revision`, `actionId` и только разрешённые semantic inputs.
15. Action response содержит только semantic patch объявленных outputs.
16. Ошибка или stale response не очищает и не перезаписывает форму.
17. `issueDate + expirationDate` генерируются, валидируются и применяются атомарно.
18. После action любое output-поле остаётся редактируемым.
19. Ручное изменение dependency не вызывает скрытый пересчёт и не очищает outputs.
20. Финальный клик Prepared напрямую вызывает `generate(mode=prepared)` без `prepare`.
21. Финальный `mode=prepared` не вызывает `ChainExecutor`, random или calculate.
22. Полнота и validation проверяются до Quote/Billing.
23. Semantic → engine mapping выполняется до render allowlist.
24. Неизвестный mode не попадает в Auto Flow.
25. Повтор одного неизменённого final snapshot не создаёт двойной charge/render благодаря стабильному idempotency key.
26. Успешный frontend использует существующий ответ `GenerateResult.barcodes[]`.
27. Все 55 профилей покрыты static/contract tests.
28. ON/AB не получают выдуманные US helper rules.
29. Vue state не сохраняет PII draft в localStorage/persisted store.
30. California и Washington browser acceptance flows из раздела 10.5 проходят.
