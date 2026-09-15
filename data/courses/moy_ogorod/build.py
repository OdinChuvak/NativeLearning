"""Build the expanded, versioned course bank; only Python's standard library is used."""
import hashlib
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parent
SOURCES = [
    ("umn-planting", "UMN Extension: Planting the vegetable garden", "https://extension.umn.edu/garden-and-home/yard-and-garden/gardening-in-minnesota/planting-the-vegetable-garden"),
    ("rhs-rotation", "RHS: Crop rotation", "https://www.rhs.org.uk/vegetables/crop-rotation"),
    ("umn-soil", "UMN Extension: Soil testing for lawns and gardens", "https://extension.umn.edu/garden-and-home/yard-and-garden/gardening-in-minnesota/soil-testing-for-lawns-and-gardens"),
    ("umd-nutrition", "University of Maryland Extension: Fertilizing Vegetable Gardens", "https://www.extension.umd.edu/resource/fertilizing-vegetable-gardens"),
    ("uc-weeds", "UC IPM: Weed Management in Vegetable Crops", "https://ipm.ucanr.edu/home-and-landscape/weed-management-in-vegetable-crops/"),
    ("umn-insects", "UMN Extension: Fruit and vegetable insects", "https://extension.umn.edu/yard-and-garden-insects/fruit-and-vegetable-insects"),
    ("uc-aphids", "UC IPM: Aphids", "https://ipm.ucanr.edu/home-and-landscape/aphids/"),
    ("uc-whiteflies", "UC IPM: Whiteflies", "https://ipm.ucanr.edu/home-and-landscape/whiteflies/"),
    ("uc-mites", "UC IPM: Spider Mites", "https://ipm.ucanr.edu/home-and-landscape/spider-mites/"),
    ("umn-seedlings", "UMN Extension: How to prevent seedling damping off", "https://extension.umn.edu/garden-and-home/yard-and-garden/gardening-in-minnesota/yard-and-garden-problems/how-to-prevent-seedling-damping-off"),
    ("umd-tomatoes", "University of Maryland Extension: Key to Common Problems of Tomatoes", "https://www.extension.umd.edu/resource/key-common-problems-tomatoes"),
    ("umn-pollinators", "UMN: Vegetable Garden Best Management Practices for Pollinators", "https://ncipmhort.cfans.umn.edu/ipm-bmp-cultural-control/vegetable-garden-best-management-practices-pollinators"),
    ("rhs-lime", "RHS: Lime and liming", "https://www.rhs.org.uk/soil-composts-mulches/lime-liming"),
    ("rhs-green", "RHS: Using Green Manures", "https://schoolgardening.rhs.org.uk/resources/info-sheet/using-green-manures.aspx"),
    ("epa-label", "EPA: Read the Label First — Protect Your Garden", "https://www.epa.gov/safepestcontrol/read-label-first-protect-your-garden"),
    ("epa-storage", "EPA: Storing Pesticides Safely", "https://www.epa.gov/safepestcontrol/storing-pesticides-safely"),
    ("umd-compost", "University of Maryland Extension: How to Make Compost at Home", "https://extension.umd.edu/resource/how-make-compost-home"),
    ("umn-harvest", "UMN Extension: Postharvest handling of fruit and vegetable crops", "https://extension.umn.edu/agriculture/specialty-crops/commercial-fruit-production/postharvest-handling-of-fruit-and-vegetable-crops-in-minnesota"),
    ("rhs-weeds", "RHS: Identify common weeds", "https://www.rhs.org.uk/weeds/identify-common-weeds"),
    ("rhs-couch", "RHS: Couch Grass", "https://www.rhs.org.uk/weeds/couch-grass"),
]

# Short learning objectives, in the same order as the authored topics.
OBJECTIVES = [
    "Оценить освещение, рельеф, ветер, воду и ограничения участка перед размещением грядок.",
    "Различать свойства почвы и выбирать уход, сохраняющий воздух, влагу и структуру.",
    "Составить план грядок с учётом размеров растений, родства культур и истории посадок.",
    "Прочитать упаковку семян, проверить всхожесть и организовать хранение.",
    "Обеспечить условия прорастания, правильно разместить семена и ухаживать за всходами.",
    "Вырастить компактную рассаду и подготовить её к пересадке без потери сортовых меток.",
    "Высадить рассаду по погоде и управлять укрытием без перегрева растений.",
    "Проверять влажность у корней и подбирать способ подачи воды без размыва и застоя.",
    "Выбрать чистую мульчу, правильно разместить её и продолжать контроль грядки.",
    "Применять рыхление, опоры и формировку с учётом культуры и сохранности корней.",
    "Различать основные овощи по выращиваемым органам и ботаническим особенностям.",
    "Собрать продукцию без лишних травм и организовать раздельное хранение культур.",
    "Вести сезонные записи и использовать наблюдения для улучшения следующего сезона.",
    "Различать жизненные циклы и способы возобновления сорняков.",
    "Распознавать распространённые однолетние сорняки по сочетанию признаков.",
    "Распознавать многолетние сорняки и учитывать жизнеспособность их подземных органов.",
    "Выбрать ручной инструмент и срок прополки без повреждения овощных растений.",
    "Сократить занос и прорастание сорняков с помощью профилактики и покрытия почвы.",
    "Проводить повторяемый осмотр, различать симптомы и подтверждать неясный диагноз.",
    "Различать мелких сосущих вредителей и выбирать наблюдение или прицельное вмешательство.",
    "Распознавать листогрызущих насекомых и защищать молодые растения от повреждений.",
    "Проверять почву и ночные укрытия при повреждении корней, стеблей и листьев.",
    "Различать группы болезней и не назначать лечение по одному неспецифичному признаку.",
    "Отличать последствия погоды, воды и ошибочного ухода от инфекционной проблемы.",
    "Узнавать естественных врагов вредителей и сохранять условия для опылителей.",
    "Сочетать профилактику, диагностику и проверку результата защитных мер.",
    "Различать элементы питания и понимать ограничения диагностики по внешнему виду.",
    "Правильно подготовить пробу и связать результат анализа с планом удобрения.",
    "Учитывать состав, происхождение, разложение и санитарные ограничения органики.",
    "Управлять воздухом, влагой и составом компоста и оценивать его зрелость.",
    "Читать маркировку минеральных удобрений и различать формы питательных элементов.",
    "Рассчитать площадь, массу, объём и концентрацию; все числа здесь учебные, не нормы внесения.",
    "Различать способы подкормки и предотвращать потери питания и ожоги.",
    "Корректировать кислотность только по обоснованной потребности почвы и культуры.",
    "Различать назначение золы и минеральных добавок, учитывая их состав и ограничения.",
    "Подбирать сидераты по срокам, семейству и способу завершения выращивания.",
    "Читать регламент биопрепарата и учитывать живой агент, условия и спектр действия.",
    "Различать классы средств, сроки ожидания и требования конкретной этикетки.",
    "Организовать хранение и обслуживание оборудования с учётом инструкции и остатков средств.",
]
TOPIC_SOURCES = [
    ["umn-planting", "umn-pollinators"], ["umn-soil"], ["rhs-rotation"],
    ["umn-planting"], ["umn-planting"], ["umn-planting", "umn-seedlings"],
    ["umn-planting", "umn-pollinators"], ["umn-planting", "umd-tomatoes"],
    ["uc-weeds"], ["umn-planting"], ["umn-planting", "rhs-rotation"],
    ["umn-harvest"], ["rhs-rotation", "rhs-green"],
    ["uc-weeds"], ["uc-weeds", "rhs-weeds"], ["rhs-weeds", "rhs-couch"], ["uc-weeds"], ["uc-weeds"],
    ["umn-insects", "umd-tomatoes"], ["uc-aphids", "uc-whiteflies", "uc-mites"],
    ["umn-insects"], ["umn-insects"], ["umn-seedlings", "umd-tomatoes"],
    ["umd-tomatoes", "umd-nutrition"], ["umn-pollinators", "uc-aphids"],
    ["umn-pollinators", "epa-label"],
    ["umd-nutrition"], ["umn-soil"], ["umd-nutrition", "umn-soil"],
    ["umd-compost"], ["umd-nutrition"], ["umd-nutrition"], ["umd-nutrition"],
    ["rhs-lime"], ["rhs-lime", "umn-soil"], ["rhs-green", "rhs-rotation"],
    ["umn-insects", "epa-label"], ["epa-label"], ["epa-storage", "epa-label"],
]


def rank(*parts):
    return hashlib.sha256("/".join(map(str, parts)).encode()).digest()


def build():
    categories = []
    topic_count = 0
    question_count = 0
    seen_questions = set()
    for line in ROOT.joinpath("source.txt").read_text(encoding="utf-8").splitlines():
        if line.startswith("# "):
            categories.append({"id": len(categories) + 1, "name": line[2:], "topics": []})
        elif line.startswith("## "):
            topic_count += 1
            topic = {"id": topic_count, "name": line[3:], "description": OBJECTIVES[topic_count - 1],
                     "source_ids": TOPIC_SOURCES[topic_count - 1], "questions": []}
            categories[-1]["topics"].append(topic)
        elif line.strip():
            question, correct = line.split("|")
            assert question.endswith("?"), question
            assert question not in seen_questions, question
            seen_questions.add(question)
            question_count += 1
            topic["questions"].append({"id": question_count, "question": question, "correct": correct})
    assert len(categories) == 3 and topic_count == 39 and question_count == 780
    for category in categories:
        assert len(category["topics"]) == 13
        for topic in category["topics"]:
            questions = topic["questions"]
            assert len(questions) == 20, topic["name"]
            pool = [q["correct"] for q in questions]
            assert len(set(pool)) == 20, topic["name"]
            order = sorted(range(20), key=lambda n: rank(topic["id"], "position", n))
            for index, q in enumerate(questions):
                correct = q.pop("correct")
                # Deterministic topic-local distractors, stored explicitly in the output.
                alternatives = sorted((a for a in pool if a != correct), key=lambda a: rank(q["id"], a))[:9]
                # Each position occurs twice per topic; no fixed answer position.
                position = order[index] % 10
                alternatives.insert(position, correct)
                q["answers"] = [{"answer": a, "is_correct": a == correct} for a in alternatives]
    course = {
        "format_version": 1,
        "course": {"name": "Мой огород", "alias": "moy_ogorod", "language": "ru",
                   "description": "Базовый курс по огородоводству: выращивание овощей, сорняки и вредители, питание и средства обработки. 39 тем, 780 вопросов; в каждом вопросе один правильный ответ.",
                   "audience": "Начинающие огородники; сроки адаптируются к местному климату.",
                   "answer_mode": "single", "score_per_question": 1,
                   "application_note": "Численные задачи — учебные расчёты, не дозировки. Применение средств определяется действующей местной регистрацией и этикеткой продукта. Иностранные источники не устанавливают разрешения для России.",
                   "source_note": "Источники для проверки общих принципов и дальнейшего чтения, а не построчные цитаты. Вопросы сформулированы самостоятельно на русском языке."},
        "sources": [{"id": sid, "title": title, "url": url, "accessed_at": "2026-09-15"} for sid, title, url in SOURCES],
        "categories": categories,
    }
    ROOT.joinpath("course.json").write_text(json.dumps(course, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    md = ["# Мой огород", "", course["course"]["description"], "", course["course"]["application_note"], "",
          "Правильный вариант отмечен **✓**. Это авторский файл с ключами ответов.", "", "## Содержание", ""]
    for category in categories:
        md.append(f"### {category['id']}. {category['name']}")
        md.extend(f"- {t['id']:02d}. {t['name']}" for t in category["topics"])
        md.append("")
    for category in categories:
        md += [f"## {category['id']}. {category['name']}", ""]
        for topic in category["topics"]:
            md += [f"### Тема {topic['id']:02d}. {topic['name']}", "", topic["description"], ""]
            for q in topic["questions"]:
                md += [f"#### Вопрос {q['id']:03d}. {q['question']}", ""]
                md += [f"{i}. {a['answer']}" + (" **✓**" if a["is_correct"] else "") for i, a in enumerate(q["answers"], 1)]
                md.append("")
    md += ["## Источники и дальнейшее чтение", "", course["course"]["source_note"], ""]
    md += [f"- [{title}]({url})" for _, title, url in SOURCES]
    ROOT.joinpath("course.md").write_text("\n".join(md) + "\n", encoding="utf-8")
    print("Built: 3 categories, 39 topics, 780 questions, 7800 answers.")


if __name__ == "__main__":
    build()
