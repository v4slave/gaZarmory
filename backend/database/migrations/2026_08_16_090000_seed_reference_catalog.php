<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $referenceData = json_decode(<<<'JSON'
{
    "activities": [
        {
            "name": "Кракен",
            "icon_path": "activity-definitions/WnQFXasOZW96WGYuBKSIicKLzNIDEFvfo19k72iH.png"
        },
        {
            "name": "Т2 Кракен",
            "icon_path": "activity-definitions/depbKfaV41qdCI6kPzBAoKG743irHTCYp0S17CSQ.png"
        },
        {
            "name": "Ксанатос",
            "icon_path": "activity-definitions/d29g6INZDZtq6fXHDEvCZT3TharWzGx35sUEhD5o.png"
        },
        {
            "name": "Месания",
            "icon_path": "activity-definitions/oTrPa5TWt3bbnkf8Vvt8KYRWzxqqzXqr345pZ9H4.png"
        },
        {
            "name": "Кошка",
            "icon_path": "activity-definitions/uBANPfEdW5jGarcZEeDnX2qi2viSzTP65rUiy8O8.png"
        },
        {
            "name": "Анталлон",
            "icon_path": "activity-definitions/Szb5S81PPwfIxu0wqZ74PAwhgKcB6Dx7DegGRIv3.png"
        },
        {
            "name": "Калеиль",
            "icon_path": "activity-definitions/WJjVnkvxZGMVO7J60tZPMYc1QVVI48N0sFHTMSl5.png"
        },
        {
            "name": "Калидис",
            "icon_path": "activity-definitions/b5SEe7PXvKI2WncDT4TkUJ6T41fGIfZ9XzU7yw8G.png"
        },
        {
            "name": "Левиафан",
            "icon_path": "activity-definitions/imlkKn8fMuPUT6WLFYJb0at6lK2Yz61rbNkQKpmh.png"
        },
        {
            "name": "Т2 Левиафан",
            "icon_path": "activity-definitions/giThWUcJr2RsRTuK9WBFUVfptZVxNLlnns8OpG7H.png"
        },
        {
            "name": "Авиара",
            "icon_path": "activity-definitions/Xp9Kdfh3rhiG7cirYTz5jofd2Cn0F2pekmktYEC7.png"
        },
        {
            "name": "АГЛ",
            "icon_path": "activity-definitions/QYfDKJPblV3khytTiVlgw2evrLDH3n6YOB8azgRM.png"
        },
        {
            "name": "Т2 АГЛ",
            "icon_path": "activity-definitions/P8Bg8QEz6r98cwTv6c15kshyZ4Tq0AXNRmwDUhuK.png"
        },
        {
            "name": "Морф",
            "icon_path": "activity-definitions/ujJUNi5YSpq2vcgMPqxw1O7wDYWc8FM0Xw8Tv2Ga.png"
        },
        {
            "name": "Марля",
            "icon_path": "activity-definitions/MKKYxC1IGnPFH28Axia1Ln7OkBM01w5TvcUdljVL.png"
        },
        {
            "name": "Т2 Марля",
            "icon_path": "activity-definitions/TYL2qeefFfiK37QiYHW5tsRcJF1hbTyxX18n8Q8C.png"
        },
        {
            "name": "Жук",
            "icon_path": "activity-definitions/fERL4AQDdexh5uWVHZsxQY4edxQpthSjo55pCcdR.png"
        }
    ],
    "loot": [
        {
            "name": "Нарвиг, огненный клинок Морфеоса",
            "icon_path": "loot-catalog/XnkdJGiIxS3oEGdEi1VKCqqSAnNElZqUKJxRHpzm.png",
            "is_active": true
        },
        {
            "name": "Свиток пробуждения драконоборца",
            "icon_path": "loot-catalog/J4Z6gpUpGQG2FBvFX9quqL8Jzfsn5TcTSy4sTKTn.png",
            "is_active": true
        },
        {
            "name": "Свиток пробуждения нуонского драконоборца",
            "icon_path": "loot-catalog/UQAxDcSCbRaLEfxuKHYdROLwHHpHEJflkHs2rTQf.png",
            "is_active": true
        },
        {
            "name": "Адамантовый коготь",
            "icon_path": "loot-catalog/MpdGUFCBFhWOtGLTmWEHgLZ9316pagO3Lu4fFjju.png",
            "is_active": true
        },
        {
            "name": "Кристаллическое перо",
            "icon_path": "loot-catalog/Jyz6BjZpKiRMb1NyhCjvd5nCaxFrSx9SVvHVamOK.png",
            "is_active": true
        },
        {
            "name": "Рави’мар, Драконья ярость",
            "icon_path": "loot-catalog/neOM2Hc2Pg0UfTnG4oD4oI7dggm20o6ONHZY7dki.png",
            "is_active": true
        },
        {
            "name": "Ташш, змеиное жало",
            "icon_path": "loot-catalog/zd8VYtH7ZWsq07Zqpk7q7kgN8dKqwupuZ00TLj6g.png",
            "is_active": true
        },
        {
            "name": "Нирах, искушающий",
            "icon_path": "loot-catalog/KZrqdELnXbeKsJksI1LrcqlFhtknutJMhMCfPyYL.png",
            "is_active": true
        },
        {
            "name": "Ро'кана, Безумие морей",
            "icon_path": "loot-catalog/A45pS52d2VepFHW6tWcq34FDIJUz5TPLnaLZ5Jpw.png",
            "is_active": true
        },
        {
            "name": "Дра'кордис, Сердце дракона",
            "icon_path": "loot-catalog/NuiR30q8xPcJXJsv2j3BbNQ56Y7Uc8PzNy6wwq4j.png",
            "is_active": true
        },
        {
            "name": "Ишхар, грань измерений",
            "icon_path": "loot-catalog/qfCOy7HrGFQu7fI2ai7Qxt3n3NYh7uNQ6IWsvgUH.png",
            "is_active": true
        },
        {
            "name": "Мор`гур, Истинная смерть",
            "icon_path": "loot-catalog/nV8r6ax2erYKIBpPBtwd969lPlEOIBLFmWDVCtGR.png",
            "is_active": true
        },
        {
            "name": "Вул`данор, Истинная тьма",
            "icon_path": "loot-catalog/t7652BZPAqcLY25DVhZHCt6UWl2JupZrQNURHxHq.png",
            "is_active": true
        },
        {
            "name": "Аст'аджал, Длань судьбы",
            "icon_path": "loot-catalog/qYugO935TQV7CAH5rbpjXd1mYsZtwIDaBC07orRq.png",
            "is_active": true
        },
        {
            "name": "Иг`нис, Истинное пламя",
            "icon_path": "loot-catalog/ScdP24dWlJ8OLoQBIZwo6wXY8pJ8boW0PtyDylD2.png",
            "is_active": true
        },
        {
            "name": "Дра`орис, Истинное разрушение",
            "icon_path": "loot-catalog/WDX559xRfKv0yske4AlnavzNLJ9JvOHTwnDPDi3a.png",
            "is_active": true
        },
        {
            "name": "Капюшон иферийского наместника",
            "icon_path": "loot-catalog/qzWyy7pWtsbhbmB9EmGwMKnTqgjrks3K6vc1XVRk.png",
            "is_active": true
        },
        {
            "name": "Мантия иферийского наместника",
            "icon_path": "loot-catalog/w4C0bzVu4IVxx2jRwSIqConUhfpHZufEOXSrPAqm.png",
            "is_active": true
        },
        {
            "name": "Перчатки иферийского наместника",
            "icon_path": "loot-catalog/wFmk000amqchc6HGN5S12rmGCwduHnMNlw1M1A4I.png",
            "is_active": true
        },
        {
            "name": "Поножи иферийского наместника",
            "icon_path": "loot-catalog/QQgJdJJzMYfoS8znK8RVdkB2t5PAfsYleOwMLeQ0.png",
            "is_active": true
        },
        {
            "name": "Сапоги иферийского наместника",
            "icon_path": "loot-catalog/UjRzOG9C9kZQxUE4CFXW1CiNgOb8qXF8wXYDrudM.png",
            "is_active": true
        },
        {
            "name": "Драго`ран, Истинная ярость",
            "icon_path": "loot-catalog/SJOF4tPqDS8gVtUly7mTx15u2IOAy27jXQCpjmQ9.png",
            "is_active": true
        },
        {
            "name": "Нерхал, бронзовая чешуя",
            "icon_path": "loot-catalog/vcEZLLWVa0wBcWH4Om1OEwE2cuCGXBTDP5VzIzez.png",
            "is_active": true
        },
        {
            "name": "Лик Ашьяры",
            "icon_path": "loot-catalog/SbUPVNflORZAJ83ncqoBicXZtLrxsYFmVaRac2aq.png",
            "is_active": true
        },
        {
            "name": "Анд'хакар, Чернильная тьма",
            "icon_path": "loot-catalog/74x4Em3zKlocWj5QKDN7TrIn7M8MsNOcl3h4Lgdn.png",
            "is_active": true
        },
        {
            "name": "Плащ проклятой души",
            "icon_path": "loot-catalog/BjaBo2ae1Tb5DwSiJ1bb99jREUx6uWhIwLBcRl6Y.png",
            "is_active": true
        },
        {
            "name": "Аметистовая гравировка «Морского дракона»",
            "icon_path": "loot-catalog/RTZ1FiFEJiHec54O7lG2qYe0Xm7z1Bld4wWrLydK.png",
            "is_active": true
        },
        {
            "name": "Аметистовая гравировка багровой грозы",
            "icon_path": "loot-catalog/lIOEEEpQbMcz6zhgyYU0ue1tO0lxvxDPQveWEFUW.png",
            "is_active": true
        },
        {
            "name": "Аметистовая гравировка кровавой длани",
            "icon_path": "loot-catalog/5Bwd0qbyhHX8UZsCo1LbuaX2PcwSRs1AH5v6L8S2.png",
            "is_active": true
        },
        {
            "name": "Аметистовая гравировка морской бездны",
            "icon_path": "loot-catalog/ZKjqlGu3vyTCUOY2gu9pEQOLmx76l3b8pEn1TBXC.png",
            "is_active": true
        },
        {
            "name": "Аметистовая гравировка пиратской метки",
            "icon_path": "loot-catalog/LxgMla9zqHNnVWvRWmfK2Q3EXedyvLvBl1pcBMcO.png",
            "is_active": true
        },
        {
            "name": "Аметистовая гравировка северной звезды",
            "icon_path": "loot-catalog/ImC67q1shMZdfIuQey3phAti08DoXiWF7avHURsW.png",
            "is_active": true
        },
        {
            "name": "Эссенция гнева",
            "icon_path": "loot-catalog/7t7ACOPLbUtGwMZHUgeicx9P3s5nYyAOg3GX0IJX.png",
            "is_active": true
        },
        {
            "name": "Эссенция кошмара",
            "icon_path": "loot-catalog/n4JxLUnI0u6tqzPr8Ig2v2t4rD6C7HCXASNJQysd.png",
            "is_active": true
        },
        {
            "name": "Эссенция ужаса",
            "icon_path": "loot-catalog/5t8ys1ICNRc8hPqlEGbyb1xjC3DyTI5hmv6YEi9k.png",
            "is_active": true
        },
        {
            "name": "Глаз Левиафана",
            "icon_path": "loot-catalog/vh0XsRag4wWx4jaCPMSEahBWogxbWrXi3CDSiye0.png",
            "is_active": true
        },
        {
            "name": "Чернильный мешочек Кракена",
            "icon_path": "loot-catalog/xgJnNQrPF8eJHPl9ZCVOITfw6cP3ySOxcTsRECs8.png",
            "is_active": true
        },
        {
            "name": "Лоскут кожи Калидиса",
            "icon_path": "loot-catalog/8Uoy4gKqZKeIZLU1AqAQdeOHaza6HtEx2Kdiumhm.png",
            "is_active": true
        },
        {
            "name": "Каменное сердце Морфеоса",
            "icon_path": "loot-catalog/JjBYLmxoU9PS3AMfwow84ioqkPl0KkOAsZmfu8rj.png",
            "is_active": true
        },
        {
            "name": "Каменное сердце Марли",
            "icon_path": "loot-catalog/5blHX38oLlXnQCIwfN9Yj2tyjVU0PhiVXtm8FgEC.png",
            "is_active": true
        },
        {
            "name": "Клык Калидиса",
            "icon_path": "loot-catalog/DjLUSwMYkzzaoyACQMgm52HMW7PZhiq0ON4ckaLo.png",
            "is_active": true
        },
        {
            "name": "Средоточие безумия",
            "icon_path": "loot-catalog/fnu4oEK1agBj8TZZUvhM51JixkWJfsuMW1Vioqhu.png",
            "is_active": true
        },
        {
            "name": "Средоточие морей",
            "icon_path": "loot-catalog/X3mG2ykFSaBKMHHT6ltspkS0R45uFUYdAMSiFmh2.png",
            "is_active": true
        },
        {
            "name": "Средоточие сумрака",
            "icon_path": "loot-catalog/HK5oWW6eNLV4GJ8heNlGCkCGz5LbN4sOxCnJIvMG.png",
            "is_active": true
        },
        {
            "name": "Средоточие ярости",
            "icon_path": "loot-catalog/9GkWtZHausFrewerDelCVvipsoJCgCnqxAV3U28r.png",
            "is_active": true
        },
        {
            "name": "Фрагмент чешуи Ашьяры",
            "icon_path": "loot-catalog/GLRZGbR398yJn41daKMc16EIYEdNK1UWSUxaOtlr.png",
            "is_active": true
        },
        {
            "name": "Глайдер «Рассекатель небес»",
            "icon_path": "loot-catalog/rMb78T0D72pjcXanS6qCFMXSyX67poT7QZ03YHfE.png",
            "is_active": true
        },
        {
            "name": "Глайдер «Властелин морей»",
            "icon_path": "loot-catalog/YcLRdlvsW45qi5yarnB2wsJA1KAVFalmvKIe7Axa.png",
            "is_active": true
        },
        {
            "name": "Трофейная эссенция стихий",
            "icon_path": "loot-catalog/IsIxxcZrBqHc3UkaNwdOZdEltKNXSJLPY95rdrNw.png",
            "is_active": true
        },
        {
            "name": "Эссенция ярости х1000",
            "icon_path": "loot-catalog/bdZpn0XiJhaWBLB5xXS7a36423fsaHzE5kKKQ5el.png",
            "is_active": true
        },
        {
            "name": "Эссенция ярости х3000",
            "icon_path": "loot-catalog/yGC30ZdwDtrzAdS2uocyoOt0om3uqTctGZX4Ijm7.png",
            "is_active": true
        },
        {
            "name": "Эссенция ярости х6000",
            "icon_path": "loot-catalog/GWyQE80s8KaeopRolfxTkdGAQWJjhI5lm5Pr3kaQ.png",
            "is_active": true
        },
        {
            "name": "Эссенция ярости х8000",
            "icon_path": "loot-catalog/taAWpVQ2EFfP134Y6VEtGdxlXsiNvBzQs8IOYuiF.png",
            "is_active": true
        },
        {
            "name": "Эссенция ярости х10000",
            "icon_path": "loot-catalog/fW3RoNqQgx9SI0THWWlmY5mJzDzvr5IgpgMgQK65.png",
            "is_active": true
        },
        {
            "name": "Глайдер \"Крылья укротителя бури\"",
            "icon_path": "loot-catalog/hjYnqIzYfb59edJPv1dgXuvaYSBLDsJJsh3Wrtkx.png",
            "is_active": true
        },
        {
            "name": "Генетический материал дракона",
            "icon_path": "loot-catalog/Cyi6f32YTdN0i4fikK5mJOLYiNClhW4B7vfnOQL2.png",
            "is_active": true
        },
        {
            "name": "Огненная чешуя",
            "icon_path": "loot-catalog/IsfegAT3BeWGg6yTI0vMUEwk2dIuo8yfK5UWh7UQ.png",
            "is_active": true
        },
        {
            "name": "Эссенция солнечного ахкиума",
            "icon_path": "loot-catalog/KWu5opbKmTT6MA7AxxqXhojdr2F4mLPR1h6UwX6Z.png",
            "is_active": true
        },
        {
            "name": "Эссенция звездного ахкиума",
            "icon_path": "loot-catalog/dlAgSsG1X1llvCd9fMB1eo35K8HRWhZyq1RbJ9Ub.png",
            "is_active": true
        },
        {
            "name": "Эссенция лунного ахкиума",
            "icon_path": "loot-catalog/NKL8NLGdar6XLjkuMdcklExmahKlCzrNfoqelZEH.png",
            "is_active": true
        },
        {
            "name": "Корона громовержца",
            "icon_path": "loot-catalog/UZgXW10VzUurkaEKNmSIIP8Be7WBDqfqYQVy3eBv.png",
            "is_active": true
        },
        {
            "name": "Джераб, слуга смерти",
            "icon_path": "loot-catalog/UJhQIS0sqz8bObELm2PTdOhZDFQFhRTU82aTsUx4.png",
            "is_active": true
        },
        {
            "name": "Гирра, пробивающий брешь",
            "icon_path": "loot-catalog/WyNjsI0WazKLtbCpvnoZOKfhaIFudldCh5nJDg04.png",
            "is_active": true
        }
    ]
}
JSON, true, 512, JSON_THROW_ON_ERROR);

        $now = now();

        foreach ($referenceData['activities'] as $activity) {
            DB::table('activity_definitions')
                ->where('name', $activity['name'])
                ->update([
                    'icon_path' => $activity['icon_path'],
                    'updated_at' => $now,
                ]);
        }

        $loot = array_map(
            static fn (array $item): array => [
                'name' => $item['name'],
                'icon_path' => $item['icon_path'],
                'is_active' => $item['is_active'],
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $referenceData['loot'],
        );

        DB::table('loot_catalog_items')->upsert(
            $loot,
            ['name'],
            ['icon_path', 'is_active', 'updated_at'],
        );
    }

    public function down(): void
    {
        // Reference data may already be used by activities and treasury records.
        // A rollback must not delete or detach it.
    }
};

